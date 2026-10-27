<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\TutorProfiles\SubmitTutorProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\AttachTutorAvatarRequest;
use App\Http\Requests\TutorProfileRequest;
use App\Http\Requests\VerificationDocumentRequest;
use App\Http\Resources\TutorProfileResource;
use App\Models\File;
use App\Models\TutorProfile;
use App\Models\VerificationDocument;
use App\Support\TutorProfileRevisions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TutorProfileController extends Controller
{
    public function show(Request $request): TutorProfileResource|JsonResponse
    {
        abort_unless($request->user()->role === 'tutor', 403, 'Only tutors can access onboarding.');
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
        return DB::transaction(function () use ($request): TutorProfileResource {
            $profile = $this->editableProfile($request);
            $data = $request->validated();
            if ($profile?->status === 'approved') {
                app(TutorProfileRevisions::class)->save($profile, $data, $data['expected_version'] ?? null);

                return new TutorProfileResource($profile);
            }
            unset($data['expected_version']);
            $specializationId = $data['specialization_id'];
            unset($data['specialization_id']);

            $profile ??= new TutorProfile(['user_id' => $request->user()->id]);
            $profile->fill($data);
            $profile->status ??= 'draft';
            $profile->save();
            $profile->specializations()->sync([$specializationId]);

            return new TutorProfileResource($profile->load(['specializations', 'verificationDocuments']));
        });
    }

    public function submit(Request $request): TutorProfileResource|JsonResponse
    {
        abort_unless($request->user()->role === 'tutor', 403, 'Only tutors can access onboarding.');
        $profile = $request->user()->tutorProfile;
        abort_unless($profile, 422, 'Create a draft profile first.');
        $profile = app(SubmitTutorProfile::class)->execute($request->user(), $profile->id);

        return new TutorProfileResource($profile->load(['specializations', 'verificationDocuments']));
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

            if (! $file->isComplete()) {
                return response()->json(['message' => 'File must be complete before it can be attached.'], 409);
            }

            if ($profile->status === 'approved') {
                app(TutorProfileRevisions::class)->save($profile, ['avatar_file_id' => $file->id], $request->validated('expected_version'));
            } else {
                $profile->update(['avatar_file_id' => $file->id]);
            }

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

            if (! $file->isComplete()) {
                return response()->json(['message' => 'File must be complete before it can be attached.'], 409);
            }

            $document = VerificationDocument::firstOrCreate(
                [
                    'tutor_profile_id' => $profile->id,
                    'file_id' => $file->id,
                ],
                ['document_type' => $data['document_type'], 'expires_at' => $data['expires_at'] ?? null],
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
        abort_if(
            $profile?->status === 'suspended',
            403,
            'Suspended profiles cannot be edited.',
        );

        if ($profile !== null) {
            abort_unless(
                in_array($profile->status, ['draft', 'changes_requested', 'approved'], true),
                409,
                'Only draft, changes-requested or approved profiles can be edited.',
            );
        }

        return $profile;
    }
}
