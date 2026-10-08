<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\FileLinkRequest;
use App\Http\Requests\FileUploadRequest;
use App\Http\Resources\FileResource;
use App\Models\File;
use App\Support\AdminPermissions;
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
        Storage::disk('local')->putFileAs(dirname($objectKey), $uploaded, basename($objectKey));

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
        if ($file->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $file->update(['scan_status' => 'clean']);

        return new FileResource($file->refresh());
    }

    public function link(FileLinkRequest $request, File $file): JsonResponse
    {
        if ($file->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (! $file->isComplete()) {
            return response()->json(['message' => 'File must be complete before it can be attached.'], 409);
        }

        $data = $request->validated();
        $link = DB::table('file_links')->updateOrInsert(
            [
                'file_id' => $file->id,
                'resource_type' => $data['resource_type'],
                'resource_id' => $data['resource_id'],
            ],
            ['purpose' => $data['purpose'] ?? null],
        );

        return response()->json(['data' => [
            'file_id' => $file->id,
            'resource_type' => $data['resource_type'],
            'resource_id' => $data['resource_id'],
            'purpose' => $data['purpose'] ?? null,
        ]], $link ? 200 : 201);
    }

    public function download(Request $request, File $file)
    {
        $isOwner = $file->owner_id === $request->user()->id;
        $isVerificationDocument = DB::table('verification_documents')
            ->where('file_id', $file->id)
            ->exists();
        $isAuthorizedReviewer = $request->user()->hasPermission(
            AdminPermissions::DOWNLOAD_VERIFICATION_DOCUMENTS,
        );

        if (! $isOwner && ! ($isVerificationDocument && $isAuthorizedReviewer)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (! $file->isComplete()) {
            return response()->json(['message' => 'File is not complete.'], 409);
        }

        abort_unless(Storage::disk($file->disk)->exists($file->object_key), 404);

        return Storage::disk($file->disk)->download($file->object_key, $file->original_name);
    }

    public function destroy(Request $request, File $file): JsonResponse
    {
        if ($file->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (DB::table('file_links')->where('file_id', $file->id)->exists()) {
            return response()->json(['message' => 'Linked files cannot be deleted.'], 409);
        }

        Storage::disk($file->disk)->delete($file->object_key);
        $file->delete();

        return response()->noContent();
    }
}
