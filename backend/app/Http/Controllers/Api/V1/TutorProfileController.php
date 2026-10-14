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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class TutorProfileController extends Controller
{
    public function show(Request $request): TutorProfileResource|JsonResponse
    {
        abort_unless($request->user()->role === 'tutor', 403, 'Only tutors can access onboarding.');
        $profile = $request->user()->tutorProfile()->with([
            'specializations',
            'verificationDocuments',
        ])->first();

        if (!$profile) {
            return response()->json(['message' => 'Tutor profile not found.'], 404);
        }

        return new TutorProfileResource($profile);
    }

    public function store(TutorProfileRequest $request): TutorProfileResource|JsonResponse
    {
        return DB::transaction(function () use ($request): TutorProfileResource {
            $profile = $this->editableProfile($request);
            $data = $request->validated();
            $specializationId = $data['specialization_id'];
            unset($data['specialization_id']);

            $profile ??= new TutorProfile(['user_id' => $request->user()->id]);
            $profile->fill($data);
            $profile->approval_status ??= 'pending';
            $profile->save();
            $profile->specializations()->sync([$specializationId]);

            return new TutorProfileResource($profile->load(['specializations', 'verificationDocuments']));
        });
    }

    public function submit(Request $request): TutorProfileResource|JsonResponse
    {
        return DB::transaction(function () use ($request): TutorProfileResource {
            $user = $request->user()->newQuery()->lockForUpdate()->findOrFail($request->user()->id);
            abort_unless($user->role === 'tutor' && $user->status === 'active', 403, 'Only active tutors can use onboarding.');
            abort_unless($user->hasVerifiedEmail(), 403, 'Email verification is required.');
            $profile = $user->tutorProfile()->lockForUpdate()->first();
            abort_unless($profile, 422, 'Create a draft profile first.');
            abort_if($profile->suspended_at, 403, 'Suspended tutor profiles cannot be submitted.');

            if ($profile->submitted_at && $profile->approval_status !== 'changes_requested') {
                return new TutorProfileResource($profile->load(['specializations', 'verificationDocuments']));
            }

            $avatar = $profile->avatarFile;
            $hasDocument = $profile->verificationDocuments()->whereHas('file', fn($query) => $query
                ->where('owner_id', $user->id)->where('purpose', 'verification_document')
                ->where('scan_status', 'clean'))->get()->contains(fn($document) => Storage::disk($document->file->disk)->exists($document->file->object_key));
            Validator::make([
                'headline' => trim($profile->headline),
                'bio' => trim($profile->bio ?? ''),
                'specialization_id' => $profile->specializations()->where('status', 'active')->value('specializations.id'),
                'avatar_file_id' => $avatar && $avatar->owner_id === $user->id && $avatar->purpose === 'avatar'
                    && $avatar->isComplete() && Storage::disk($avatar->disk)->exists($avatar->object_key) ? $avatar->id : null,
                'verification_document_file_id' => $hasDocument ? 1 : null,
            ], [
                'headline' => ['required'],
                'bio' => ['required'],
                'specialization_id' => ['required'],
                'avatar_file_id' => ['required'],
                'verification_document_file_id' => ['nullable'],
            ])->validate();

            $profile->forceFill([
                'submitted_at' => now(),
                'approval_status' => 'pending',
                'review_reason' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ])->save();

            return new TutorProfileResource($profile->refresh()->load(['specializations', 'verificationDocuments']));
        });
    }

    public function attachAvatar(AttachTutorAvatarRequest $request): TutorProfileResource|JsonResponse
    {
        return DB::transaction(function () use ($request): TutorProfileResource|JsonResponse {
            $profile = $this->editableProfile($request);
            abort_unless($profile, 422, 'Create a draft profile first.');

            $file = File::lockForUpdate()->findOrFail($request->validated('file_id'));

            if ($file->owner_id !== $request->user()->id) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }

            if ($file->purpose !== 'avatar') {
                return response()->json(['message' => 'File purpose must be avatar.'], 422);
            }

            if (!$file->isComplete()) {
                return response()->json(['message' => 'File must be complete before it can be attached.'], 409);
            }

            $profile->update(['avatar_file_id' => $file->id]);

            return new TutorProfileResource($profile->load([
                'specializations',
                'verificationDocuments',
            ]));
        });
    }

    public function createVerificationDocument(
        VerificationDocumentRequest $request,
    ): JsonResponse {
        return DB::transaction(function () use ($request): JsonResponse {
            $profile = $this->editableProfile($request);
            abort_unless($profile, 422, 'Create a draft profile first.');

            $data = $request->validated();
            $file = File::lockForUpdate()->findOrFail($data['file_id']);

            if ($file->owner_id !== $request->user()->id) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }

            if ($file->purpose !== 'verification_document') {
                return response()->json(['message' => 'File purpose must be verification_document.'], 422);
            }

            if (!$file->isComplete()) {
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
        });
    }

    private function editableProfile(Request $request): ?TutorProfile
    {
        $user = $request->user()->newQuery()->lockForUpdate()->findOrFail($request->user()->id);
        abort_unless($user->role === 'tutor' && $user->status === 'active', 403, 'Only active tutors can use onboarding.');
        $profile = $user->tutorProfile()->lockForUpdate()->first();
        abort_if($profile?->suspended_at, 403, 'Suspended profiles cannot be edited.');
        abort_if($profile?->submitted_at && $profile->approval_status !== 'changes_requested', 409, 'Submitted profile cannot be edited.');

        return $profile;
    }
}