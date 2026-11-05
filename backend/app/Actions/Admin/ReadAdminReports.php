<?php

namespace App\Actions\Admin;

use App\Models\Program;
use App\Models\ServiceListing;
use App\Models\User;
use App\Support\TrustWorkflow;
use App\Support\TutorEligibility;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReadAdminReports
{
    public function index(Request $request, TrustWorkflow $flow): JsonResponse
    {
        $scope = $request->query('scope', 'mine');
        if ($scope === 'mine') {
            $flow->permission($request, 'admin.reports.view');

            return $this->listing($request, $flow, true);
        }
        $flow->permission($request, 'admin.reports.assign');
        $data = $request->validate(['scope' => 'required|in:unassigned,all', 'per_page' => 'sometimes|integer|min:1|max:50']);
        $query = DB::table('reports');
        if ($scope === 'unassigned') {
            $query->whereNull('assigned_admin_id')->where('status', 'open');
        }
        $rows = $query->orderByDesc('id')->paginate($data['per_page'] ?? 20);

        return response()->json(['data' => $rows->getCollection()->map(fn ($row) => [
            'id' => (int) $row->id, 'status' => $row->status, 'assigned_admin_id' => $row->assigned_admin_id,
            'assignment_version' => (int) $row->assignment_version,
        ]), 'meta' => ['current_page' => $rows->currentPage(), 'per_page' => $rows->perPage(), 'total' => $rows->total()]]);
    }

    private function listing(Request $request, TrustWorkflow $flow, bool $admin): JsonResponse
    {
        $data = $request->validate(['per_page' => 'sometimes|integer|min:1|max:50']);
        $rows = DB::table('reports')->where($admin ? 'assigned_admin_id' : 'reporter_id', $request->user()->id)->orderByDesc('id')->paginate($data['per_page'] ?? 20);

        return response()->json(['data' => $rows->getCollection()->map(fn ($row) => $flow->reportData($row)),
            'meta' => ['current_page' => $rows->currentPage(), 'per_page' => $rows->perPage(), 'total' => $rows->total()]]);
    }

    public function show(Request $request, int $report, TrustWorkflow $flow): JsonResponse
    {
        return response()->json(['data' => $flow->reportData($flow->report($request, $report))]);
    }

    public function evidence(Request $request, int $report, TrustWorkflow $flow): JsonResponse
    {
        return DB::transaction(function () use ($request, $report, $flow): JsonResponse {
            $flow->lockActor($request);
            $row = $flow->report($request, $report, 'admin.reports.view', true);
            if ($row->status !== 'open') {
                $flow->fail($request, 403, 'report_evidence_closed', 'Evidence access ends when the Report closes.');
            }
            $data = $this->evidenceData($request, $row);
            $flow->audit($request, 'evidence.read', 'report', $report, null, ['available' => $data !== null]);

            return response()->json(['data' => $data ?? ['code' => 'evidence_unavailable', 'report_id' => $report]], 200, ['Cache-Control' => 'private, no-store']);
        }, 3);
    }

    private function evidenceData(Request $request, object $report): ?array
    {
        if ($report->resource_type === 'message') {
            $message = DB::table('messages')->find($report->resource_id);
            if (! $message) {
                return null;
            }
            $base = DB::table('messages')->where('conversation_id', $message->conversation_id);
            $earlier = (clone $base)->where(fn ($q) => $q->where('created_at', '<', $message->created_at)->orWhere(fn ($q) => $q->where('created_at', $message->created_at)->where('id', '<', $message->id)))
                ->orderByDesc('created_at')->orderByDesc('id')->limit(10)->get(['id', 'body', 'created_at'])->reverse()->values();
            $later = (clone $base)->where(fn ($q) => $q->where('created_at', '>', $message->created_at)->orWhere(fn ($q) => $q->where('created_at', $message->created_at)->where('id', '>', $message->id)))
                ->orderBy('created_at')->orderBy('id')->limit(5)->get(['id', 'body', 'created_at']);

            return ['resource_type' => 'message', 'messages' => $earlier->concat([(object) ['id' => $message->id, 'body' => $message->body, 'created_at' => $message->created_at]])->concat($later)->map(fn ($m) => ['id' => (int) $m->id, 'body' => $m->body, 'created_at' => CarbonImmutable::parse($m->created_at)->utc()->toISOString()])->values()->all()];
        }
        [$table, $fields] = match ($report->resource_type) {
            'program' => ['programs', ['id', 'status', 'version']],
            'listing' => ['service_listings', ['id', 'title', 'description', 'status']],
            'tutor_profile' => ['tutor_profiles', ['id', 'headline', 'bio', 'status']],
            'review' => ['reviews', ['id', 'rating', 'comment', 'visibility']],
            default => [null, []],
        };
        if ($table === null) {
            return null;
        }
        if (in_array($report->resource_type, ['listing', 'tutor_profile'], true) && ! $this->canReport($request, $report->resource_type, (int) $report->resource_id)) {
            return null;
        }
        $row = DB::table($table)->where('id', $report->resource_id)->first($fields);

        if (! $row) {
            return null;
        }
        $history = DB::table('audit_logs')->where('resource_type', $report->resource_type)
            ->where('resource_id', $report->resource_id)
            ->whereIn('action', ['review.hide', 'review.restore', 'listing.hide', 'listing.restore'])
            ->orderByDesc('id')->limit(20)->get(['id', 'action', 'reason', 'created_at']);

        return ['resource_type' => $report->resource_type, 'resource' => (array) $row, 'moderation_history' => $history->all()];
    }

    private function canReport(Request $request, string $type, int $id): bool
    {
        if ($type === 'program') {
            return Program::visibleTo($request->user())->whereKey($id)->exists();
        }
        if ($type === 'message') {
            $row = DB::table('messages')->find($id);

            return $row && DB::table('conversation_members')->where('conversation_id', $row->conversation_id)->where('user_id', $request->user()->id)->exists();
        }
        if ($type === 'review') {
            $row = DB::table('reviews')->find($id);

            if (! $row) {
                return false;
            }
            if ((int) $row->customer_id === $request->user()->id) {
                return Program::visibleTo($request->user())->whereKey($row->program_id)->exists();
            }
            $profile = DB::table('tutor_profiles')->find($row->tutor_profile_id);
            $owner = $profile ? User::find($profile->user_id) : null;

            return $row->visibility === 'visible' && $owner
                && app(TutorEligibility::class)->canManagePrograms($owner, $profile, CarbonImmutable::now('UTC'))
                && DB::table('programs')->where('id', $row->program_id)->where('owner_tutor_profile_id', $row->tutor_profile_id)->where('status', 'completed')->exists()
                && DB::table('program_members')->where('program_id', $row->program_id)->where('user_id', $row->customer_id)->where('member_role', 'customer')->exists();
        }
        $profile = $type === 'tutor_profile' ? DB::table('tutor_profiles')->find($id) : null;
        if ($type === 'listing') {
            $listing = ServiceListing::query()->visiblePublic()->whereKey($id)->first();
            if (! $listing || DB::table('listing_moderation_holds')->where('listing_id', $id)->whereNull('restored_at')->exists()) {
                return false;
            }
            $profile = DB::table('tutor_profiles')->find($listing->tutor_profile_id);
        }
        $owner = $profile ? User::find($profile->user_id) : null;

        return $owner && app(TutorEligibility::class)->canManagePrograms($owner, $profile, CarbonImmutable::now('UTC'));
    }
}
