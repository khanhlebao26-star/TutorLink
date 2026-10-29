<?php

namespace App\Actions\Files;

use App\Models\File;
use App\Models\User;
use App\Support\ApiErrorResponse;
use App\Support\ZipScanGuard;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CompleteFile
{
    /** @return array{file:File,status:int,code:?string} */
    public function execute(
        User $actor,
        int $fileId,
        string $requestId,
    ): array {
        // ponytail: bounded file validation holds actor/file locks;
        // use a leased job with revision checks if throughput requires it.
        return DB::transaction(function () use ($actor, $fileId, $requestId): array {
            $actor = User::whereKey($actor->id)
                ->lockForUpdate()
                ->first();

            if ($actor === null || $actor->status !== 'active') {
                throw new HttpResponseException(
                    ApiErrorResponse::make(
                        403,
                        'account_inactive',
                        'This account is not available.',
                        $requestId,
                    )
                );
            }

            $file = File::whereKey($fileId)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $file->owner_id !== (int) $actor->id) {
                throw new HttpResponseException(
                    ApiErrorResponse::make(
                        403,
                        'file_forbidden',
                        'Forbidden.',
                        $requestId,
                    )
                );
            }

            if ($file->scan_status === 'rejected') {
                return [
                    'file' => $file,
                    'status' => 422,
                    'code' => 'file_rejected',
                ];
            }

            if (
                $file->disk !== 'local'
                || $file->visibility !== 'private'
            ) {
                return $this->finish(
                    $file,
                    'rejected',
                    422,
                    'file_rejected',
                );
            }

            try {
                $bytes = $this->snapshot($file);
            } catch (Throwable $exception) {
                return $this->finish(
                    $file,
                    'pending',
                    503,
                    'file_storage_unavailable',
                );
            }

            if (! $this->matchesMetadata($file, $bytes)) {
                return $this->finish(
                    $file,
                    'rejected',
                    422,
                    'file_rejected',
                );
            }

            $timeout = min(
                10,
                max(
                    0.01,
                    (float) config('file-validation.archive_timeout_seconds', 10),
                ),
            );

            $deadline = hrtime(true)
                + (int) ($timeout * 1_000_000_000);

            try {
                $archiveAllowed = app(ZipScanGuard::class)->allows($bytes, $deadline);
            } catch (Throwable $exception) {
                return $this->finish(
                    $file,
                    'pending',
                    503,
                    'file_validation_unavailable',
                );
            }

            if (! $archiveAllowed) {
                return $this->finish(
                    $file,
                    'rejected',
                    422,
                    'file_rejected',
                );
            }

            try {
                $currentBytes = $this->snapshot($file);
            } catch (Throwable $exception) {
                return $this->finish(
                    $file,
                    'pending',
                    503,
                    'file_storage_unavailable',
                );
            }

            if (
                ! hash_equals(
                    hash('sha256', $bytes),
                    hash('sha256', $currentBytes),
                )
            ) {
                return $this->finish(
                    $file,
                    'rejected',
                    422,
                    'file_rejected',
                );
            }

            if ($file->isComplete()) {
                return [
                    'file' => $file,
                    'status' => 200,
                    'code' => null,
                ];
            }

            return $this->finish(
                $file,
                'clean',
                200,
                null,
            );
        }, 3);
    }

    private function snapshot(File $file): string
    {
        $stream = Storage::disk($file->disk)
            ->readStream($file->object_key);

        if (! is_resource($stream)) {
            throw new \RuntimeException(
                'Stored object cannot be read.'
            );
        }

        try {
            $bytes = stream_get_contents(
                $stream,
                (int) config('file-validation.max_bytes', 10485760) + 1,
            );

            if ($bytes === false) {
                throw new \RuntimeException(
                    'Stored object cannot be read.'
                );
            }

            return $bytes;
        } finally {
            fclose($stream);
        }
    }

    private function matchesMetadata(File $file, string $bytes): bool
    {
        $limits = [
            'avatar' => 2097152,
            'verification_document' => 10485760,
            'task_attachment' => 10485760,
            'profile_document' => 10485760,
            'message_attachment' => 5242880,
        ];

        $size = strlen($bytes);

        if (
            ! isset($limits[$file->purpose])
            || $size === 0
            || $size > $limits[$file->purpose]
            || $size !== (int) $file->size_bytes
            || ! preg_match(
                '/\A[a-f0-9]{64}\z/',
                $file->checksum_sha256 ?? '',
            )
            || ! hash_equals(
                $file->checksum_sha256,
                hash('sha256', $bytes),
            )
        ) {
            return false;
        }

        $extension = strtolower(
            pathinfo(
                $file->original_name ?? '',
                PATHINFO_EXTENSION,
            )
        );

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);

        $allowed = match ($extension) {
            'pdf' => $mime === 'application/pdf'
                && str_starts_with($bytes, '%PDF-'),

            'jpg', 'jpeg' => $mime === 'image/jpeg'
                && str_starts_with($bytes, "\xff\xd8\xff"),

            'png' => $mime === 'image/png'
                && str_starts_with($bytes, "\x89PNG\r\n\x1a\n"),

            'doc' => $mime === 'application/msword'
                && str_starts_with(
                    $bytes,
                    "\xd0\xcf\x11\xe0\xa1\xb1\x1a\xe1",
                ),

            'docx' => $mime
                === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                && str_starts_with($bytes, "PK\x03\x04"),

            default => false,
        };

        return $allowed
            && $mime === $file->mime_type
            && (
                $file->purpose !== 'avatar'
                || in_array($extension, ['jpg', 'jpeg', 'png'], true)
            );
    }

    /**
     * @param  array<string,mixed>  $evidence
     * @return array{file:File,status:int,code:?string}
     */
    private function finish(
        File $file,
        string $scanStatus,
        int $status,
        ?string $code,
        array $evidence = [],
    ): array {
        $file->forceFill(array_replace([
            'scan_status' => $scanStatus,
            'scan_engine' => null,
            'scan_version' => null,
            'scanned_sha256' => null,
            'scanned_at' => null,
        ], $evidence))->save();

        // Commit rejected/pending before creating the HTTP error response.
        return [
            'file' => $file->refresh(),
            'status' => $status,
            'code' => $code,
        ];
    }
}
