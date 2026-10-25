<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\RevokeUserSessions;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminDecisionRequest;
use App\Http\Resources\TutorProfileResource;
use App\Http\Resources\UserResource;
use App\Models\TutorProfile;
use App\Models\User;
use App\Support\AdminPermissions;
use App\Support\NotificationOutboxWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, AdminPermissions::VIEW_TUTOR_PROFILES);

        $status = $request->query('status', 'submitted');
        $allowedStatuses = ['submitted', 'changes_requested', 'approved', 'rejected', 'suspended'];

        if (!in_array($status, $allowedStatuses, true)) {
            return response()->json(['message' => 'Invalid tutor profile status.'], 422);
        }

        $profiles = TutorProfile::query()
            ->with(['user', 'specializations', 'verificationDocuments'])
            ->where('status', $status)
            ->orderByDesc('submitted_at')
            ->get()
            ->map(fn(TutorProfile $profile): array => $this->profilePayload($profile));

        return response()->json(['data' => $profiles]);
    }

    public function show(Request $request, TutorProfile $tutorProfile): JsonResponse
    {
        $this->requirePermission($request, AdminPermissions::VIEW_TUTOR_PROFILES);

        return response()->json([
            'data' => $this->profilePayload(
                $tutorProfile->load('user'),
                $request->user()->hasPermission(AdminPermissions::DOWNLOAD_VERIFICATION_DOCUMENTS),
            ),
        ]);
    }

    public function approve(AdminDecisionRequest $request, TutorProfile $tutorProfile): JsonResponse
    {
        return $this->decide($request, $tutorProfile, 'approved', 'tutor_profile_approved');
    }

    public function requestChanges(AdminDecisionRequest $request, TutorProfile $tutorProfile): JsonResponse
    {
        return $this->decide($request, $tutorProfile, 'changes_requested', 'tutor_profile_changes_requested');
    }

    public function reject(AdminDecisionRequest $request, TutorProfile $tutorProfile): JsonResponse
    {
        return $this->decide($request, $tutorProfile, 'rejected', 'tutor_profile_rejected');
    }

    public function suspendProfile(AdminDecisionRequest $request, TutorProfile $tutorProfile): JsonResponse
    {
        return $this->changeProfileSuspension($request, $tutorProfile, true);
    }

    public function restoreProfile(AdminDecisionRequest $request, TutorProfile $tutorProfile): JsonResponse
    {
        return $this->changeProfileSuspension($request, $tutorProfile, false);
    }

    private function changeProfileSuspension(
        AdminDecisionRequest $request,
        TutorProfile $tutorProfile,
        bool $suspend,
    ): JsonResponse {
        $actor = $this->requirePermission($request, AdminPermissions::SUSPEND_TUTOR_PROFILES);
        $data = $request->validated();

        $profile = DB::transaction(function () use ($actor, $data, $suspend, $tutorProfile): TutorProfile {
            $profile = TutorProfile::query()
                ->with('user')
                ->lockForUpdate()
                ->findOrFail($tutorProfile->id);

            $expectedStatus = $suspend ? 'approved' : 'suspended';

            if ($profile->status !== $expectedStatus) {
                abort(409, 'The requested profile status transition is not allowed.');
            }

            $before = $this->profileState($profile);
            $profile->forceFill([
                'status' => $suspend ? 'suspended' : 'approved',
                'suspended_at' => $suspend ? now() : null,
                'suspension_reason' => $suspend ? $data['reason'] : null,
            ])->save();
            $notificationType = $suspend
                ? 'tutor_profile_suspended'
                : 'tutor_profile_restored';

            $auditId = $this->writeAudit(
                $actor,
                $notificationType,
                'tutor_profile',
                $profile->id,
                $before,
                $this->profileState($profile),
                $data['reason'],
            );

            $verb = $suspend ? 'suspend' : 'restore';

            app(NotificationOutboxWriter::class)->record(
                $notificationType,
                'tutor_profile',
                $profile->id,
                "profile:{$profile->id}:{$verb}:{$auditId}",
                [$profile->user_id],
                ['deep_link' => '/profile'],
            );

            return $profile->refresh()->load('user');
        });

        return response()->json([
            'data' => $this->profilePayload(
                $profile,
                $actor->hasPermission(AdminPermissions::DOWNLOAD_VERIFICATION_DOCUMENTS),
            ),
        ]);
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
        AdminDecisionRequest $request,
        TutorProfile $tutorProfile,
        string $status,
        string $action,
    ): JsonResponse {
        $actor = $this->requirePermission($request, AdminPermissions::DECIDE_TUTOR_PROFILES);
        $data = $request->validated();

        $profile = DB::transaction(function () use ($actor, $data, $status, $action, $tutorProfile): TutorProfile {
            $profile = TutorProfile::query()
                ->with('user')
                ->lockForUpdate()
                ->findOrFail($tutorProfile->id);

            if ($profile->status !== 'submitted') {
                abort(409, 'Only submitted profiles can be decided.');
            }

            if ($profile->user->status !== 'active') {
                abort(409, 'This account is not available.');
            }

            $before = $this->profileState($profile);
            $profile->forceFill([
                'status' => $status,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_reason' => $data['reason'],
            ])->save();
            $auditId = $this->writeAudit(
                $actor,
                $action,
                'tutor_profile',
                $profile->id,
                $before,
                $this->profileState($profile),
                $data['reason'],
            );

            $verb = match ($action) {
                'tutor_profile_approved' => 'approve',
                'tutor_profile_changes_requested' => 'changes-requested',
                'tutor_profile_rejected' => 'reject',
            };

            app(NotificationOutboxWriter::class)->record(
                $action,
                'tutor_profile',
                $profile->id,
                "profile:{$profile->id}:{$verb}:{$auditId}",
                [$profile->user_id],
                ['deep_link' => '/profile'],
            );

            return $profile->refresh()->load('user');
        });

        return response()->json([
            'data' => $this->profilePayload(
                $profile,
                $actor->hasPermission(AdminPermissions::DOWNLOAD_VERIFICATION_DOCUMENTS),
            ),
        ]);
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

            if (!$canTransition) {
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

        abort_unless($user instanceof User && $user->hasPermission($permission), 403, 'Permission denied.');

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
                    'files.original_name',
                    'files.mime_type',
                    'files.size_bytes',
                    'files.scan_status',
                ])
                ->map(fn(object $document): array => [
                    'id' => (int) $document->id,
                    'tutor_profile_id' => $profile->id,
                    'file_id' => (int) $document->file_id,
                    'document_type' => $document->document_type,
                    'status' => $document->status,
                    'reviewed_at' => $document->reviewed_at,
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
            $actor->hasPermission(AdminPermissions::DECIDE_TUTOR_PROFILES)
            && $profile->status === 'submitted'
            && $profile->user->status === 'active'
        ) {
            $actions = ['approve', 'request-changes', 'reject'];
        }

        if ($actor->hasPermission(AdminPermissions::SUSPEND_TUTOR_PROFILES)) {
            if ($profile->status === 'approved') {
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
     * @return array<string, mixed>
     */
    private function profileState(TutorProfile $profile): array
    {
        return [
            'status' => $profile->status,
            'reviewed_by' => $profile->reviewed_by,
            'reviewed_at' => $profile->reviewed_at?->toISOString(),
            'review_reason' => $profile->review_reason,
            'suspended_at' => $profile->suspended_at?->toISOString(),
            'suspension_reason' => $profile->suspension_reason,
        ];
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