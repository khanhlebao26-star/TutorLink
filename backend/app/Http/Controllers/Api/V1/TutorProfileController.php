<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttachTutorAvatarRequest;
use App\Http\Requests\TutorProfileRequest;
use App\Http\Requests\VerificationDocumentRequest;
use App\Http\Resources\TutorProfileResource;
use App\Models\File;
use App\Models\TutorProfile;
use App\Models\VerificationDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TutorProfileController extends Controller
{
    public function show(Request $request): TutorProfileResource|JsonResponse
    {
        $profile = $request->user()->tutorProfile()->with([
            'specializations',
            'verificationDocuments',
        ])->first();

        if (! $profile) {
            return response()->json(['message' => 'Tutor profile not found.'], 404);
        }

        return new TutorProfileResource($profile);
    }

    public function store(TutorProfileRequest $request): TutorProfileResource|JsonResponse
    {
        $profile = $request->user()->tutorProfile()->first();

        if ($profile?->suspended_at) {
            return response()->json(['message' => 'Suspended tutor profiles cannot be edited.'], 403);
        }

        if ($profile?->submitted_at && $profile->approval_status !== 'changes_requested') {
            return response()->json(['message' => 'Submitted profile cannot be edited.'], 409);
        }

        $data = $request->validated();
        $specializationId = $data['specialization_id'];
        unset($data['specialization_id']);

        $profile ??= new TutorProfile(['user_id' => $request->user()->id]);
        $profile->fill($data);
        $profile->approval_status ??= 'pending';
        $profile->save();
        $profile->specializations()->sync([$specializationId]);

        return new TutorProfileResource($profile->load([
            'specializations',
            'verificationDocuments',
        ]));
    }

    public function submit(Request $request): TutorProfileResource|JsonResponse
    {
        $profile = $request->user()->tutorProfile()->with([
            'specializations',
            'verificationDocuments',
        ])->first();

        if (! $profile) {
            return response()->json(['message' => 'Create a draft profile first.'], 422);
        }

        if ($profile->suspended_at) {
            return response()->json(['message' => 'Suspended tutor profiles cannot be submitted.'], 403);
        }

        if ($profile->submitted_at && $profile->approval_status !== 'changes_requested') {
            return new TutorProfileResource($profile);
        }

        $profile->forceFill([
            'submitted_at' => now(),
            'approval_status' => 'pending',
            'review_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ])->save();

        return new TutorProfileResource($profile->refresh()->load([
            'specializations',
            'verificationDocuments',
        ]));
    }

    public function attachAvatar(AttachTutorAvatarRequest $request): TutorProfileResource|JsonResponse
    {
        $profile = $request->user()->tutorProfile()->first();

        if (! $profile) {
            return response()->json(['message' => 'Create a draft profile first.'], 422);
        }

        if ($profile->suspended_at) {
            return response()->json(['message' => 'Suspended profiles cannot be edited.'], 403);
        }

        if ($profile->submitted_at && $profile->approval_status !== 'changes_requested') {
            return response()->json(['message' => 'Submitted profile cannot be edited.'], 409);
        }

        $file = File::findOrFail($request->validated('file_id'));

        if ($file->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($file->purpose !== 'avatar') {
            return response()->json(['message' => 'File purpose must be avatar.'], 422);
        }

        if (! $file->isComplete()) {
            return response()->json(['message' => 'File must be complete before it can be attached.'], 409);
        }

        $profile->update(['avatar_file_id' => $file->id]);

        return new TutorProfileResource($profile->load([
            'specializations',
            'verificationDocuments',
        ]));
    }

    public function createVerificationDocument(
        VerificationDocumentRequest $request,
    ): JsonResponse {
        $profile = $request->user()->tutorProfile()->first();

        if (! $profile) {
            return response()->json(['message' => 'Create a draft profile first.'], 422);
        }

        if ($profile->suspended_at) {
            return response()->json(['message' => 'Suspended profiles cannot be edited.'], 403);
        }

        if ($profile->submitted_at && $profile->approval_status !== 'changes_requested') {
            return response()->json(['message' => 'Submitted profile cannot be edited.'], 409);
        }

        $data = $request->validated();
        $file = File::findOrFail($data['file_id']);

        if ($file->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($file->purpose !== 'verification_document') {
            return response()->json(['message' => 'File purpose must be verification_document.'], 422);
        }

        if (! $file->isComplete()) {
            return response()->json(['message' => 'File must be complete before it can be attached.'], 409);
        }

        $document = VerificationDocument::updateOrCreate(
            [
                'tutor_profile_id' => $profile->id,
                'file_id' => $file->id,
            ],
            ['document_type' => $data['document_type']],
        );

        return response()->json([
            'data' => [
                'id' => $document->id,
                'tutor_profile_id' => $document->tutor_profile_id,
                'file_id' => $document->file_id,
                'document_type' => $document->document_type,
                'status' => $document->status,
            ],
        ], $document->wasRecentlyCreated ? 201 : 200);
    }
}
