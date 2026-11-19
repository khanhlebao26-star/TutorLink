<?php

namespace App\Actions\Admin;

use App\Http\Resources\TutorProfileResource;
use App\Http\Resources\UserResource;
use App\Models\TutorProfile;
use App\Policies\TutorProfilePolicy;
use App\Support\AdminPermissions;
use App\Support\TutorProfileRevisions;
use App\Support\TutorProfileWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReadAdminProfiles
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate(['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'], 'sort' => ['sometimes', Rule::in(['submitted_desc', 'id_desc'])]]);
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
        $query = TutorProfile::query()->select('tutor_profiles.*')->selectSub(DB::table('tutor_profile_revisions')->selectRaw('1')->whereColumn('tutor_profile_id', 'tutor_profiles.id')->where('status', 'submitted')->limit(1), 'has_submitted_revision')->whereHas('user')->where('status', $status);
        if (($filters['sort'] ?? 'submitted_desc') === 'submitted_desc') {
            $query->orderByDesc('submitted_at');
        }
        $query->orderByDesc('id');
        if ($scope !== 'mine') {
            $query->select(['id', 'status', 'submitted_at', 'assigned_admin_id', 'assigned_by', 'assigned_at', 'assignment_version']);
        }
        if ($scope === 'mine') {
            $query->where('assigned_admin_id', $actor->id)->with(['user:id,full_name,email,role,status,date_of_birth,email_verified_at', 'specializations:id,name', 'verificationDocuments']);
        } elseif ($scope === 'unassigned') {
            $query->whereNull('assigned_admin_id');
        }
        $page = $query->paginate($filters['per_page'] ?? 20);
        if ($scope === 'mine') {
            $revisions = DB::table('tutor_profile_revisions')->whereIn('tutor_profile_id', $page->getCollection()->pluck('id'))
                ->selectRaw('DISTINCT ON (tutor_profile_id) *')->orderBy('tutor_profile_id')->orderByDesc('revision_no')->get()->keyBy('tutor_profile_id');
            $page->getCollection()->each(fn (TutorProfile $profile) => $profile->setRelation('latestRevisionData', $revisions->get($profile->id)));
        }
        $items = $page->getCollection()->map(fn (TutorProfile $profile): array => $scope === 'mine'
            ? $this->profilePayload($profile)
            : ['id' => $profile->id, 'status' => $profile->status, 'submitted_at' => $profile->submitted_at?->toISOString(),
                ...$flow->assignment($profile)]);

        return response()->json(['data' => $items, 'meta' => ['current_page' => $page->currentPage(), 'total' => $page->total(), 'per_page' => $page->perPage()]]);
    }

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

    public function profilePayload(TutorProfile $profile, bool $includeDocuments = false): array
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

    private function allowedActions(TutorProfile $profile): array
    {
        $actor = request()->user();
        $actions = [];

        if (
            app(TutorProfilePolicy::class)->decide($actor, $profile)
            && ($profile->status === 'submitted' || ($profile->status === 'approved'
                && ($profile->relationLoaded('latestRevisionData') ? (bool) $profile->getAttribute('has_submitted_revision') : DB::table('tutor_profile_revisions')->where('tutor_profile_id', $profile->id)->where('status', 'submitted')->exists())))
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
}
