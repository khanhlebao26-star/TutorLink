<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\FileLinkRequest;
use App\Http\Requests\FileUploadRequest;
use App\Http\Resources\FileResource;
use App\Models\File;
use App\Models\TutorProfile;
use App\Models\VerificationDocument;
use App\Policies\TutorProfilePolicy;
use App\Support\TutorProfileWorkflow;
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

    public function complete(Request $request, File $file): FileResource|JsonResponse
    {
        return DB::transaction(function () use ($request, $file): FileResource|JsonResponse {
            $file = File::lockForUpdate()->findOrFail($file->id);
            if ($file->owner_id !== $request->user()->id) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }

            abort_unless(Storage::disk($file->disk)->exists($file->object_key), 409, 'Uploaded file is missing.');
            abort_if($file->scan_status === 'rejected', 409, 'Rejected files cannot be completed.');
            $disk = Storage::disk($file->disk);
            abort_unless(
                $disk->size($file->object_key) === (int) $file->size_bytes
                && $disk->mimeType($file->object_key) === $file->mime_type
                && hash_equals($file->checksum_sha256 ?? '', hash('sha256', $disk->get($file->object_key))),
                409,
                'Uploaded file does not match its metadata.'
            );
            $file->update(['scan_status' => 'clean']);

            return new FileResource($file->refresh());
        });
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
                app(TutorProfileWorkflow::class)->audit($actor, 'verification_document.download', 'verification_document',
                    $document->id, [], ['file_id' => $file->id, 'tutor_profile_id' => $profile->id]);
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
