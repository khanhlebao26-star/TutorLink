<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Accounts\ChangeAccountStatus;
use App\Actions\Admin\ReadAdminProfiles;
use App\Actions\TutorProfiles\AssignTutorProfile;
use App\Actions\TutorProfiles\DecideTutorProfile;
use App\Actions\TutorProfiles\DecideVerificationDocument;
use App\Actions\TutorProfiles\SuspendTutorProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminDecisionRequest;
use App\Http\Requests\AssignTutorProfileRequest;
use App\Http\Requests\TutorProfileDecisionRequest;
use App\Http\Requests\VerificationDocumentDecisionRequest;
use App\Http\Resources\UserResource;
use App\Models\TutorProfile;
use App\Models\User;
use App\Models\VerificationDocument;
use App\Policies\TutorProfilePolicy;
use App\Support\TutorProfileWorkflow;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    private const ERROR_TYPE = 'array{code:string,message:string,request_id:string,errors:array<string,mixed>}';

    private const ASSIGNMENT_TYPE = 'array{id:int,assigned_admin_id:int|null,assigned_by:int|null,assigned_at:string|null,assignment_version:int}';

    private const PROFILE_RESULT_TYPE = 'array{data:array{user:array<string,mixed>,allowed_actions:list<string>,profile:array<string,mixed>,assignment:array<string,mixed>,revision:array<string,mixed>|null,verification_documents:list<array<string,mixed>>|null}}';

    private const DOCUMENT_RESULT_TYPE = 'array{data:array{id:int,tutor_profile_id:int,status:string,reviewed_by:int|null,reviewed_at:string|null,review_reason:string|null,expires_at:string|null}}';

    private const SUSPENSION_RESULT_TYPE = 'array{data:array{profile:array{id:int,status:string,status_before_suspension:string|null,suspended_at:string|null},user:array{id:int,status:string}}}';

    /** List assigned profiles, or minimal assignment queue metadata for coordinators. */
    #[OpenApiResponse(status: 200, description: 'List assigned profiles, or minimal assignment queue metadata for coordinators.', type: 'array{data:list<array<string,mixed>>,meta:array{current_page:int,total:int,per_page:int}}')]
    #[OpenApiResponse(status: 403, description: 'The actor lacks the required permission.', type: self::ERROR_TYPE)]
    public function index(Request $request): JsonResponse
    {
        return app(ReadAdminProfiles::class)->index($request);
    }

    /** Read a profile within the current assignment scope. */
    #[OpenApiResponse(status: 200, description: 'Read a profile within the current assignment scope.', type: self::PROFILE_RESULT_TYPE)]
    #[OpenApiResponse(status: 403, description: 'The actor lacks the required permission.', type: self::ERROR_TYPE)]
    public function show(Request $request, TutorProfile $tutorProfile): JsonResponse
    {
        return app(ReadAdminProfiles::class)->show($request, $tutorProfile);
    }

    /** Assign, reassign or revoke the reviewer using the current assignment version. */
    #[OpenApiResponse(status: 200, description: 'Assign, reassign or revoke the reviewer using the current assignment version.', type: 'array{data:'.self::ASSIGNMENT_TYPE.'}')]
    #[OpenApiResponse(status: 409, description: 'The assignment, revision version or resource state has changed.', type: self::ERROR_TYPE)]
    #[OpenApiResponse(status: 403, description: 'The actor lacks the required permission.', type: self::ERROR_TYPE)]
    public function assign(AssignTutorProfileRequest $request, TutorProfile $tutorProfile): JsonResponse
    {
        $profile = app(AssignTutorProfile::class)->execute($request->user(), $tutorProfile->id, $request->validated());

        return response()->json(['data' => ['id' => $profile->id, ...app(TutorProfileWorkflow::class)->assignment($profile)]]);
    }

    /** Approve one complete and unexpired pending verification document. */
    #[OpenApiResponse(status: 200, description: 'Approve one complete and unexpired pending verification document.', type: self::DOCUMENT_RESULT_TYPE)]
    #[OpenApiResponse(status: 409, description: 'The assignment, revision version or resource state has changed.', type: self::ERROR_TYPE)]
    #[OpenApiResponse(status: 403, description: 'The actor lacks the required permission.', type: self::ERROR_TYPE)]
    public function approveDocument(VerificationDocumentDecisionRequest $request, VerificationDocument $document): JsonResponse
    {
        return $this->decideDocument($request, $document, true);
    }

    /** Reject one pending verification document with a reason. */
    #[OpenApiResponse(status: 200, description: 'Reject one pending verification document with a reason.', type: self::DOCUMENT_RESULT_TYPE)]
    #[OpenApiResponse(status: 409, description: 'The assignment, revision version or resource state has changed.', type: self::ERROR_TYPE)]
    #[OpenApiResponse(status: 403, description: 'The actor lacks the required permission.', type: self::ERROR_TYPE)]
    public function rejectDocument(VerificationDocumentDecisionRequest $request, VerificationDocument $document): JsonResponse
    {
        return $this->decideDocument($request, $document, false);
    }

    private function decideDocument(VerificationDocumentDecisionRequest $request, VerificationDocument $document, bool $approve): JsonResponse
    {
        $document = app(DecideVerificationDocument::class)->execute($request->user(), $document->id, $approve, $request->validated());

        return response()->json(['data' => $document->only(['id', 'tutor_profile_id', 'status', 'reviewed_by', 'reviewed_at', 'review_reason', 'expires_at'])]);
    }

    /** Approve a submitted profile or a versioned revision after document checks. */
    #[OpenApiResponse(status: 200, description: 'Approve a submitted profile or a versioned revision after document checks.', type: self::PROFILE_RESULT_TYPE)]
    #[OpenApiResponse(status: 409, description: 'The assignment, revision version or resource state has changed.', type: self::ERROR_TYPE)]
    #[OpenApiResponse(status: 403, description: 'The actor lacks the required permission.', type: self::ERROR_TYPE)]
    public function approve(TutorProfileDecisionRequest $request, TutorProfile $tutorProfile): JsonResponse
    {
        return $this->decide($request, $tutorProfile, 'approved');
    }

    /** Request changes to a submitted profile or revision, preserving approved content. */
    #[OpenApiResponse(status: 200, description: 'Request changes to a submitted profile or revision, preserving approved content.', type: self::PROFILE_RESULT_TYPE)]
    #[OpenApiResponse(status: 409, description: 'The assignment, revision version or resource state has changed.', type: self::ERROR_TYPE)]
    #[OpenApiResponse(status: 403, description: 'The actor lacks the required permission.', type: self::ERROR_TYPE)]
    public function requestChanges(TutorProfileDecisionRequest $request, TutorProfile $tutorProfile): JsonResponse
    {
        return $this->decide($request, $tutorProfile, 'changes_requested');
    }

    /** Reject a submitted profile or revision without allowing self-resubmission. */
    #[OpenApiResponse(status: 200, description: 'Reject a submitted profile or revision without allowing self-resubmission.', type: self::PROFILE_RESULT_TYPE)]
    #[OpenApiResponse(status: 409, description: 'The assignment, revision version or resource state has changed.', type: self::ERROR_TYPE)]
    #[OpenApiResponse(status: 403, description: 'The actor lacks the required permission.', type: self::ERROR_TYPE)]
    public function reject(TutorProfileDecisionRequest $request, TutorProfile $tutorProfile): JsonResponse
    {
        return $this->decide($request, $tutorProfile, 'rejected');
    }

    /** Suspend a profile and its active account atomically with both required permissions. */
    #[OpenApiResponse(status: 200, description: 'Suspend a profile and its active account atomically with both required permissions.', type: self::SUSPENSION_RESULT_TYPE)]
    #[OpenApiResponse(status: 409, description: 'The assignment, revision version or resource state has changed.', type: self::ERROR_TYPE)]
    #[OpenApiResponse(status: 403, description: 'The actor lacks the required permission.', type: self::ERROR_TYPE)]
    public function suspendProfile(AdminDecisionRequest $request, TutorProfile $tutorProfile): JsonResponse
    {
        return $this->changeProfileSuspension($request, $tutorProfile, true);
    }

    /** Restore the saved profile state while keeping account restoration separate. */
    #[OpenApiResponse(status: 200, description: 'Restore the saved profile state while keeping account restoration separate.', type: self::SUSPENSION_RESULT_TYPE)]
    #[OpenApiResponse(status: 409, description: 'The assignment, revision version or resource state has changed.', type: self::ERROR_TYPE)]
    #[OpenApiResponse(status: 403, description: 'The actor lacks the required permission.', type: self::ERROR_TYPE)]
    public function restoreProfile(AdminDecisionRequest $request, TutorProfile $tutorProfile): JsonResponse
    {
        return $this->changeProfileSuspension($request, $tutorProfile, false);
    }

    private function changeProfileSuspension(
        AdminDecisionRequest $request,
        TutorProfile $tutorProfile,
        bool $suspend,
    ): JsonResponse {
        $profile = app(SuspendTutorProfile::class)->execute($request->user(), $tutorProfile->id, $suspend, $request->validated('reason'));

        return response()->json(['data' => ['profile' => $profile->only(['id', 'status', 'status_before_suspension', 'suspended_at']),
            'user' => $profile->user->only(['id', 'status'])]]);
    }

    public function suspendAccount(AdminDecisionRequest $request, User $user): JsonResponse
    {
        return $this->changeAccountStatus($request, $user, 'suspended', 'account_suspended');
    }

    public function restoreAccount(AdminDecisionRequest $request, User $user): JsonResponse
    {
        return $this->changeAccountStatus($request, $user, 'active', 'account_restored');
    }

    private function decide(
        TutorProfileDecisionRequest $request,
        TutorProfile $tutorProfile,
        string $status,
    ): JsonResponse {
        $profile = app(DecideTutorProfile::class)->execute($request->user(), $tutorProfile->id, $status, $request->validated());

        return response()->json(['data' => $this->profilePayload($profile,
            app(TutorProfilePolicy::class)->download($request->user(), $profile))]);
    }

    private function changeAccountStatus(
        AdminDecisionRequest $request,
        User $target,
        string $status,
        string $action,
    ): JsonResponse {
        $target = app(ChangeAccountStatus::class)->execute(
            $request->user(), $target->id, $status === 'suspended', $request->validated()['reason'],
        );

        return response()->json(['data' => (new UserResource($target))->resolve()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(TutorProfile $profile, bool $includeDocuments = false): array
    {
        return app(ReadAdminProfiles::class)->profilePayload($profile, $includeDocuments);
    }

    /** @return list<string> */
}
