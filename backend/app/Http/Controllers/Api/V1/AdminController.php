<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\RevokeUserSessions;
use App\Actions\TutorProfiles\AssignTutorProfile;
use App\Actions\TutorProfiles\DecideTutorProfile;
use App\Actions\TutorProfiles\DecideVerificationDocument;
use App\Actions\TutorProfiles\SuspendTutorProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminDecisionRequest;
use App\Http\Requests\AssignTutorProfileRequest;
use App\Http\Requests\TutorProfileDecisionRequest;
use App\Http\Requests\VerificationDocumentDecisionRequest;
use App\Http\Resources\TutorProfileResource;
use App\Http\Resources\UserResource;
use App\Models\TutorProfile;
use App\Models\User;
use App\Models\VerificationDocument;
use App\Policies\TutorProfilePolicy;
use App\Support\AdminPermissions;
use App\Support\TutorProfileRevisions;
use App\Support\TutorProfileWorkflow;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $actor = $request->user();
        $policy = app(TutorProfilePolicy::class);
        $flow = app(TutorProfileWorkflow::class);
        $canView = $policy->permission($actor, AdminPermissions::VIEW_TUTOR_PROFILES);
        $canAssign = $policy->permission($actor, AdminPermissions::ASSIGN_TUTOR_PROFILES);
        $flow->ensure($canView || $canAssign, 403, 'permission_denied', 'Permission denied.');
        $status = $request->query('status', 'submitted');
        $allowedStatuses = ['submitted', 'changes_requested', 'approved', 'rejected', 'suspended'];
        if (! in_array($status, $allowedStatuses, true)) {
            return response()->json(['message' => 'Invalid tutor profile status.'], 422);
        }
        $scope = $request->query('scope', $canView ? 'mine' : 'unassigned');
        $flow->ensure(in_array($scope, ['mine', 'unassigned', 'all'], true), 422, 'invalid_assignment_scope', 'Invalid assignment scope.');
        $flow->ensure($scope === 'mine' ? $canView : $canAssign, 403, 'permission_denied', 'Permission denied.');
        $query = TutorProfile::query()->whereHas('user')->where('status', $status)->orderByDesc('submitted_at')->orderByDesc('id');
        if ($scope === 'mine') {
            $query->where('assigned_admin_id', $actor->id)->with(['user', 'specializations', 'verificationDocuments']);
        } elseif ($scope === 'unassigned') {
            $query->whereNull('assigned_admin_id');
        }
        $page = $query->paginate(min(100, max(1, (int) $request->query('per_page', 20))));
        $items = $page->getCollection()->map(fn (TutorProfile $profile): array => $scope === 'mine'
            ? $this->profilePayload($profile)
            : ['id' => $profile->id, 'status' => $profile->status, 'submitted_at' => $profile->submitted_at?->toISOString(),
                ...$flow->assignment($profile)]);

        return response()->json(['data' => $items, 'meta' => ['current_page' => $page->currentPage(), 'total' => $page->total(), 'per_page' => $page->perPage()]]);
    }

    /** Read a profile within the current assignment scope. */
    #[OpenApiResponse(status: 200, description: 'Read a profile within the current assignment scope.', type: self::PROFILE_RESULT_TYPE)]
    #[OpenApiResponse(status: 403, description: 'The actor lacks the required permission.', type: self::ERROR_TYPE)]
    public function show(Request $request, TutorProfile $tutorProfile): JsonResponse
    {
        return DB::transaction(function () use ($request, $tutorProfile): JsonResponse {
            $flow = app(TutorProfileWorkflow::class);
            [$actor, $profile] = $flow->lock($request->user(), $tutorProfile->id);
            $policy = app(TutorProfilePolicy::class);
            $flow->ensure($policy->view($actor, $profile), 404, 'tutor_profile_not_found', 'Tutor profile not found.');
            $includeDocuments = $policy->download($actor, $profile);
            if ($includeDocuments) {
                $flow->audit($actor, 'verification_document.view', 'tutor_profile', $profile->id, [], ['access' => 'metadata']);
            }

            return response()->json(['data' => $this->profilePayload($profile, $includeDocuments)]);
        });
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
        $actor = $this->requirePermission($request, AdminPermissions::SUSPEND_ACCOUNTS);
        $data = $request->validated();

        if ($actor->id === $target->id) {
            return response()->json(['message' => 'Administrators cannot suspend or restore themselves.'], 422);
        }

        $target = DB::transaction(function () use ($actor, $data, $status, $action, $target): User {
            $target = User::query()->lockForUpdate()->findOrFail($target->id);
            $canTransition = match ($status) {
                'suspended' => $target->status === 'active',
                'active' => $target->status === 'suspended',
                default => false,
            };

            if (! $canTransition) {
                abort(409, 'The requested account status transition is not allowed.');
            }

            $before = [
                'status' => $target->status,
                'suspended_at' => $target->suspended_at?->toISOString(),
            ];

            $updateData = [
                'status' => $status,
                'suspended_at' => $status === 'suspended' ? now() : null,
            ];

            if ($status === 'suspended') {
                $updateData['remember_token'] = str()->random(60);
            }

            $target->forceFill($updateData)->save();

            if ($status === 'suspended') {
                $target->tokens()->delete();
                app(RevokeUserSessions::class)->handle($target);
            }

            $after = [
                'status' => $target->status,
                'suspended_at' => $target->suspended_at?->toISOString(),
            ];

            $this->writeAudit($actor, $action, 'user', $target->id, $before, $after, $data['reason']);

            return $target->refresh();
        });

        return response()->json(['data' => (new UserResource($target))->resolve()]);
    }

    private function requirePermission(Request $request, string $permission): User
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->status === 'active' && $user->hasPermission($permission), 403, 'Permission denied.');

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(TutorProfile $profile, bool $includeDocuments = false): array
    {
        $payload = [
            'user' => (new UserResource($profile->user))->resolve(),
            'allowed_actions' => $this->allowedActions($profile),
            'profile' => (new TutorProfileResource($profile))->resolve(),
            'assignment' => app(TutorProfileWorkflow::class)->assignment($profile),
            'revision' => app(TutorProfileRevisions::class)->latest($profile),
        ];

        if ($includeDocuments) {
            $payload['verification_documents'] = DB::table('verification_documents as documents')
                ->join('files', 'files.id', '=', 'documents.file_id')
                ->where('documents.tutor_profile_id', $profile->id)
                ->orderByDesc('documents.created_at')
                ->get([
                    'documents.id',
                    'documents.file_id',
                    'documents.document_type',
                    'documents.status',
                    'documents.reviewed_at',
                    'documents.reviewed_by',
                    'documents.review_reason',
                    'documents.expires_at',
                    'files.original_name',
                    'files.mime_type',
                    'files.size_bytes',
                    'files.scan_status',
                ])
                ->map(fn (object $document): array => [
                    'id' => (int) $document->id,
                    'tutor_profile_id' => $profile->id,
                    'file_id' => (int) $document->file_id,
                    'document_type' => $document->document_type,
                    'status' => $document->status,
                    'reviewed_at' => $document->reviewed_at,
                    'reviewed_by' => $document->reviewed_by,
                    'review_reason' => $document->review_reason,
                    'expires_at' => $document->expires_at,
                    'original_name' => $document->original_name,
                    'mime_type' => $document->mime_type,
                    'size_bytes' => (int) $document->size_bytes,
                    'scan_status' => $document->scan_status,
                    'download_url' => route('files.download', ['file' => $document->file_id]),
                ])
                ->values()
                ->all();
        } else {
            $payload['verification_documents'] = null;
        }

        return $payload;
    }

    /** @return list<string> */
    private function allowedActions(TutorProfile $profile): array
    {
        $actor = request()->user();
        $actions = [];

        if (
            app(TutorProfilePolicy::class)->decide($actor, $profile)
            && ($profile->status === 'submitted' || ($profile->status === 'approved'
                && DB::table('tutor_profile_revisions')->where('tutor_profile_id', $profile->id)->where('status', 'submitted')->exists()))
            && $profile->user->status === 'active'
        ) {
            $actions = ['approve', 'request-changes', 'reject'];
        }

        if ($actor->hasPermission(AdminPermissions::SUSPEND_TUTOR_PROFILES)) {
            if ($profile->status !== 'suspended' && ($profile->user->status !== 'active' || $actor->hasPermission(AdminPermissions::SUSPEND_ACCOUNTS))) {
                $actions[] = 'suspend';
            } elseif ($profile->status === 'suspended') {
                $actions[] = 'restore';
            }
        }

        if (
            $actor->hasPermission(AdminPermissions::SUSPEND_ACCOUNTS)
            && $actor->id !== $profile->user_id
        ) {
            if ($profile->user->status === 'active') {
                $actions[] = 'account-suspend';
            } elseif ($profile->user->status === 'suspended') {
                $actions[] = 'account-restore';
            }
        }

        return $actions;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function writeAudit(
        User $actor,
        string $action,
        string $resourceType,
        int $resourceId,
        array $before,
        array $after,
        string $reason,
    ): int {
        return DB::table('audit_logs')->insertGetId([
            'actor_id' => $actor->id,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'before' => json_encode($before, JSON_THROW_ON_ERROR),
            'after' => json_encode($after, JSON_THROW_ON_ERROR),
            'reason' => $reason,
        ]);
    }
}
