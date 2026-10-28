<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Files\CompleteFile;
use App\Http\Controllers\Controller;
use App\Http\Requests\FileLinkRequest;
use App\Http\Requests\FileUploadRequest;
use App\Http\Resources\FileResource;
use App\Models\File;
use App\Models\TutorProfile;
use App\Models\VerificationDocument;
use App\Policies\TutorProfilePolicy;
use App\Support\ApiErrorResponse;
use App\Support\TutorProfileWorkflow;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileController extends Controller
{
    public function show(Request $request, File $file): FileResource|JsonResponse
    {
        if ($file->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return new FileResource($file);
    }

    public function store(FileUploadRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($data['purpose'] !== 'avatar' && ! $request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email verification is required.'], 403);
        }

        $uploaded = $request->file('file');
        $objectKey = 'uploads/'.$request->user()->id.'/'.Str::uuid().'.'.$uploaded->extension();
        abort_unless(Storage::disk('local')->putFileAs(dirname($objectKey), $uploaded, basename($objectKey)), 500, 'Unable to store uploaded file.');

        $file = File::create([
            'owner_id' => $request->user()->id,
            'disk' => 'local',
            'object_key' => $objectKey,
            'original_name' => $uploaded->getClientOriginalName(),
            'mime_type' => $uploaded->getMimeType(),
            'size_bytes' => $uploaded->getSize(),
            'visibility' => 'private',
            'checksum_sha256' => hash_file('sha256', $uploaded->getRealPath()),
            'scan_status' => 'pending',
            'purpose' => $data['purpose'],
        ]);

        return (new FileResource($file))->response()->setStatusCode(201);
    }

    /** Complete a private file after metadata checks and a conclusive ClamAV scan. */
    #[OpenApiResponse(200, description: 'Complete file with scan evidence bound to its SHA-256.', type: 'array{data:FileResource}')]
    #[OpenApiResponse(403, description: 'Actor is inactive or does not own the file; legacy account middleware may return message only.', type: 'array{message:string,code?:string,request_id?:string,errors?:object}')]
    #[OpenApiResponse(422, description: 'File rejected by metadata policy or malware scan; rejection is persisted.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[OpenApiResponse(503, description: 'Scanner or storage unavailable; file remains pending. Retry-After: 10.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    public function complete(
        Request $request,
        File $file,
        CompleteFile $complete,
    ): JsonResponse {
        $requestId = (string) (
            $request->attributes->get('request_id') ?: Str::uuid()
        );

        $result = $complete->execute(
            $request->user(),
            $file->id,
            $requestId,
        );

        if ($result['status'] !== 200) {
            $message = match ($result['code']) {
                'scanner_unavailable' => 'The scanner could not complete its checks. Retry later.',

                'file_storage_unavailable' => 'The uploaded object cannot be read. Retry later.',

                default => 'The file was rejected. Upload a new valid file.',
            };

            $response = ApiErrorResponse::make(
                $result['status'],
                $result['code'],
                $message,
                $requestId,
            );

            if ($result['status'] === 503) {
                $response->headers->set('Retry-After', '10');
            }

            return $response->header(
                'Cache-Control',
                'private, no-store',
            );
        }

        return (new FileResource($result['file']))
            ->response()
            ->header('X-Request-ID', $requestId)
            ->header('Cache-Control', 'private, no-store');
    }

    public function link(FileLinkRequest $request, File $file): JsonResponse
    {
        $data = $request->validated();
        abort_unless($file->owner_id === $request->user()->id, 403, 'Forbidden.');
        abort_unless($data['resource_type'] === 'verification_document', 409, 'File linking for this resource is not available yet.');

        return DB::transaction(function () use ($request, $file, $data): JsonResponse {
            $user = $request->user()->newQuery()->lockForUpdate()->findOrFail($request->user()->id);
            abort_unless($user->status === 'active', 403, 'This account is not available.');
            $document = VerificationDocument::findOrFail($data['resource_id']);
            $profile = TutorProfile::lockForUpdate()->findOrFail($document->tutor_profile_id);
            abort_unless($profile->user_id === $user->id, 403, 'Forbidden.');
            abort_if(
                $profile->status === 'suspended',
                403,
                'Suspended profiles cannot be edited.',
            );

            abort_unless(
                in_array($profile->status, ['draft', 'changes_requested'], true),
                409,
                'Only draft or changes-requested profiles can be edited.',
            );
            $file = File::lockForUpdate()->findOrFail($file->id);
            abort_unless($file->isComplete(), 409, 'File must be complete before it can be attached.');
            abort_unless($document->file_id === $file->id && $file->purpose === 'verification_document', 422, 'File does not match this verification document.');

            DB::table('file_links')->updateOrInsert([
                'file_id' => $file->id,
                'resource_type' => $data['resource_type'],
                'resource_id' => $data['resource_id'],
            ], ['purpose' => $data['purpose'] ?? null]);

            return response()->json([
                'data' => [
                    'file_id' => $file->id,
                    'resource_type' => $data['resource_type'],
                    'resource_id' => $data['resource_id'],
                    'purpose' => $data['purpose'] ?? null,
                ],
            ]);
        });
    }

    public function download(Request $request, File $file)
    {
        return DB::transaction(function () use ($request, $file) {
            $isOwner = $file->owner_id === $request->user()->id;
            if (! $isOwner) {
                $document = VerificationDocument::where('file_id', $file->id)->first();
                abort_unless($document, 403, 'Forbidden.');
                [$actor, $profile] = app(TutorProfileWorkflow::class)->lock($request->user(), $document->tutor_profile_id);
                abort_unless(app(TutorProfilePolicy::class)->download($actor, $profile), 403, 'Forbidden.');
                app(TutorProfileWorkflow::class)->audit(
                    $actor,
                    'verification_document.download',
                    'verification_document',
                    $document->id,
                    [],
                    ['file_id' => $file->id, 'tutor_profile_id' => $profile->id]
                );
            }
            abort_unless($file->isComplete(), 409, 'File is not complete.');
            abort_unless(Storage::disk($file->disk)->exists($file->object_key), 404);

            return Storage::disk($file->disk)->download($file->object_key, $file->original_name);
        });
    }

    public function destroy(Request $request, File $file): JsonResponse
    {
        return DB::transaction(function () use ($request, $file): JsonResponse {
            $file = File::lockForUpdate()->findOrFail($file->id);
            if ($file->owner_id !== $request->user()->id) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }

            if (
                DB::table('file_links')->where('file_id', $file->id)->exists()
                || DB::table('verification_documents')->where('file_id', $file->id)->exists()
                || DB::table('tutor_profiles')->where('avatar_file_id', $file->id)->exists()
                || DB::table('tutor_profile_revisions')->where('avatar_file_id', $file->id)->exists()
                || DB::table('customer_profiles')->where('avatar_file_id', $file->id)->exists()
            ) {
                return response()->json(['message' => 'Linked files cannot be deleted.'], 409);
            }

            if (Storage::disk($file->disk)->exists($file->object_key)) {
                abort_unless(Storage::disk($file->disk)->delete($file->object_key), 500, 'Unable to delete stored file.');
            }
            $file->delete();

            return response()->json(status: 204);
        });
    }
}
