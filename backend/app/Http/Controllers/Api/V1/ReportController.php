<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\ServiceListing;
use App\Models\User;
use App\Support\NotificationOutboxWriter;
use App\Support\TrustWorkflow;
use App\Support\TutorEligibility;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /** Store scoped Report data. */
    #[Response(200, description: 'Authorized result.', type: 'array{data:array{id:int,resource_type:string,resource_id:int,reason_code:string,description:?string,status:string,assigned_admin_id:?int,assignment_version:int,resolution_note:?string,handled_at:?string,created_at:?string}}')]
    #[Response(201, description: 'Created atomically.', type: 'array{data:array{id:int,resource_type:string,resource_id:int,reason_code:string,description:?string,status:string,assigned_admin_id:?int,assignment_version:int,resolution_note:?string,handled_at:?string,created_at:?string}}')]
    #[HeaderParameter('Idempotency-Key', 'UUID for a 30-day canonical request receipt.', required: true, type: 'string', format: 'uuid')]
    #[Response(401, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(403, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(404, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(409, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(422, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(429, description: 'Report quota reached; Retry-After gives seconds to retry.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    public function store(Request $request, TrustWorkflow $flow): JsonResponse
    {
        $flow->fields($request, ['resource_type', 'resource_id', 'reason_code', 'description']);
        $data = $request->validate([
            'resource_type' => 'required|in:tutor_profile,listing,message,program,review',
            'resource_id' => 'required|integer:strict|min:1|max:9007199254740991',
            'reason_code' => 'required|in:spam,harassment,inappropriate_content,fraud_suspected,misleading_information,privacy_violation,other',
            'description' => ['required_if:reason_code,other', 'nullable', 'string', 'max:2000', 'regex:/\S/u'],
        ]);
        $payload = [...$data, 'description' => $data['description'] ?? null];

        return DB::transaction(function () use ($request, $flow, $payload): JsonResponse {
            $actor = $flow->lockActor($request);
            abort_unless(in_array($actor->role, ['customer', 'tutor'], true), 403);
            if (! $this->canReport($request, $payload['resource_type'], $payload['resource_id'])) {
                $flow->fail($request, 404, 'resource_not_found', 'Resource not found.');
            }
            if ($snapshot = $flow->replay($request, $payload)) {
                return response()->json($snapshot);
            }
            if (DB::table('reports')->where('reporter_id', $actor->id)->where('resource_type', $payload['resource_type'])->where('resource_id', $payload['resource_id'])->where('status', 'open')->exists()) {
                $flow->fail($request, 409, 'report_already_open', 'An open Report already exists.');
            }
            $waits = [];
            foreach ([[10, 3600], [30, 86400]] as [$limit, $seconds]) {
                $query = DB::table('reports')->where('reporter_id', $actor->id)->where('created_at', '>', now('UTC')->subSeconds($seconds));
                if ((clone $query)->count() >= $limit) {
                    $waits[] = max(1, (int) ceil(CarbonImmutable::parse($query->min('created_at'))->addSeconds($seconds)->getTimestamp() - now('UTC')->getTimestamp()));
                }
            }
            if ($waits !== []) {
                return response()->json(['code' => 'report_rate_limited', 'message' => 'Report limit reached.', 'request_id' => $request->attributes->get('request_id'), 'errors' => (object) []], 429, ['Retry-After' => (string) max($waits)]);
            }
            $id = DB::table('reports')->insertGetId([
                'reporter_id' => $actor->id, 'resource_type' => $payload['resource_type'], 'resource_id' => $payload['resource_id'],
                'reason' => $payload['reason_code'], 'details' => $payload['description'], 'status' => 'open',
                'created_at' => now('UTC'), 'updated_at' => now('UTC'),
            ]);
            $flow->audit($request, 'report.create', 'report', $id, null, ['status' => 'open']);
            $result = ['data' => $flow->reportData(DB::table('reports')->find($id))];
            $flow->receipt($request, $payload, $result);

            return response()->json($result, 201);
        }, 3);
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

    /** Mine scoped Report data. */
    #[Response(200, description: 'Authorized result.', type: 'array{data:list<array{id:int,resource_type:string,resource_id:int,reason_code:string,description:?string,status:string,assigned_admin_id:?int,assignment_version:int,resolution_note:?string,handled_at:?string,created_at:?string}>,meta:array{current_page:int,per_page:int,total:int}}')]
    #[Response(401, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(403, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(404, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(409, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(422, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    public function mine(Request $request, TrustWorkflow $flow): JsonResponse
    {
        return $this->listing($request, $flow, false);
    }

    /** Index scoped Report data. */
    #[Response(200, description: 'Authorized result.', type: 'array{data:list<array{id:int,status:string,assigned_admin_id:?int,assignment_version:int}>,meta:array{current_page:int,per_page:int,total:int}}')]
    #[Response(401, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(403, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(404, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(409, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(422, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
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

    /** Owndetail scoped Report data. */
    #[Response(200, description: 'Authorized result.', type: 'array{data:array{id:int,resource_type:string,resource_id:int,reason_code:string,description:?string,status:string,assigned_admin_id:?int,assignment_version:int,resolution_note:?string,handled_at:?string,created_at:?string}}')]
    #[Response(401, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(403, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(404, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(409, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(422, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    public function ownDetail(Request $request, int $report, TrustWorkflow $flow): JsonResponse
    {
        $row = DB::table('reports')->where('id', $report)->where('reporter_id', $request->user()->id)->first();
        abort_unless($row, 404);

        return response()->json(['data' => $flow->reportData($row)]);
    }

    /** Show scoped Report data. */
    #[Response(200, description: 'Authorized result.', type: 'array{data:array{id:int,resource_type:string,resource_id:int,reason_code:string,description:?string,status:string,assigned_admin_id:?int,assignment_version:int,resolution_note:?string,handled_at:?string,created_at:?string}}')]
    #[Response(401, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(403, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(404, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(409, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(422, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    public function show(Request $request, int $report, TrustWorkflow $flow): JsonResponse
    {
        return response()->json(['data' => $flow->reportData($flow->report($request, $report))]);
    }

    /** Assign scoped Report data. */
    #[Response(200, description: 'Authorized result.', type: 'array{data:array{id:int,assigned_admin_id:?int,assignment_version:int}}')]
    #[Response(401, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(403, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(404, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(409, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(422, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    public function assign(Request $request, int $report, TrustWorkflow $flow): JsonResponse
    {
        $flow->permission($request, 'admin.reports.assign');
        $flow->fields($request, ['assigned_admin_id', 'assignment_version', 'reason']);
        $data = $request->validate(['assigned_admin_id' => 'present|nullable|integer:strict|min:1', 'assignment_version' => 'required|integer:strict|min:0', 'reason' => ['required', 'string', 'max:2000', 'regex:/\S/u']]);

        return DB::transaction(function () use ($request, $report, $flow, $data): JsonResponse {
            $ids = array_filter([$request->user()->id, $data['assigned_admin_id']]);
            $actors = User::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $flow->permission($request, 'admin.reports.assign');
            $target = $data['assigned_admin_id'] === null ? null : $actors->get($data['assigned_admin_id']);
            if ($data['assigned_admin_id'] !== null && (! $target || $target->status !== 'active' || ! $target->hasPermission('admin.reports.view') || ! $target->hasPermission('admin.reports.decide'))) {
                $flow->fail($request, 422, 'invalid_assigned_admin', 'Assigned Admin must be active with Report view and decide permissions.');
            }
            $row = DB::table('reports')->where('id', $report)->lockForUpdate()->first();
            abort_unless($row, 404);
            if ((int) $row->assignment_version !== $data['assignment_version']) {
                $flow->fail($request, 409, 'stale_assignment_version', 'Report assignment has changed.');
            }
            DB::table('reports')->where('id', $report)->update(['assigned_admin_id' => $data['assigned_admin_id'],
                'assigned_by' => $request->user()->id, 'assigned_at' => now('UTC'), 'assignment_version' => $row->assignment_version + 1, 'updated_at' => now('UTC')]);
            $flow->audit($request, 'assignment.change', 'report', $report,
                ['assigned_admin_id' => $row->assigned_admin_id, 'assignment_version' => (int) $row->assignment_version],
                ['assigned_admin_id' => $data['assigned_admin_id'], 'assignment_version' => $row->assignment_version + 1], $data['reason']);

            return response()->json(['data' => ['id' => $report, 'assigned_admin_id' => $data['assigned_admin_id'], 'assignment_version' => $row->assignment_version + 1]]);
        }, 3);
    }

    /** Resolve scoped Report data. */
    #[Response(200, description: 'Authorized result.', type: 'array{data:array{id:int,resource_type:string,resource_id:int,reason_code:string,description:?string,status:string,assigned_admin_id:?int,assignment_version:int,resolution_note:?string,handled_at:?string,created_at:?string}}')]
    #[Response(401, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(403, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(404, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(409, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(422, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[BodyParameter('assignment_version', required: true, type: 'int')]
    #[BodyParameter('resolution_note', required: true, type: 'string')]
    public function resolve(Request $request, int $report, TrustWorkflow $flow): JsonResponse
    {
        return $this->decide($request, $report, $flow, 'resolved');
    }

    /** Dismiss scoped Report data. */
    #[Response(200, description: 'Authorized result.', type: 'array{data:array{id:int,resource_type:string,resource_id:int,reason_code:string,description:?string,status:string,assigned_admin_id:?int,assignment_version:int,resolution_note:?string,handled_at:?string,created_at:?string}}')]
    #[Response(401, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(403, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(404, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(409, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(422, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[BodyParameter('assignment_version', required: true, type: 'int')]
    #[BodyParameter('resolution_note', required: true, type: 'string')]
    public function dismiss(Request $request, int $report, TrustWorkflow $flow): JsonResponse
    {
        return $this->decide($request, $report, $flow, 'dismissed');
    }

    private function decide(Request $request, int $report, TrustWorkflow $flow, string $status): JsonResponse
    {
        $flow->fields($request, ['assignment_version', 'resolution_note']);
        $data = $request->validate(['assignment_version' => 'required|integer:strict|min:0', 'resolution_note' => ['required', 'string', 'max:2000', 'regex:/\S/u']]);

        return DB::transaction(function () use ($request, $report, $flow, $status, $data): JsonResponse {
            $flow->lockActor($request);
            $row = $flow->report($request, $report, 'admin.reports.decide', true);
            if ($row->status !== 'open' || (int) $row->assignment_version !== $data['assignment_version']) {
                $flow->fail($request, 409, 'stale_report_decision', 'Report state or assignment has changed.');
            }
            DB::table('reports')->where('id', $report)->update(['status' => $status, 'handled_by' => $request->user()->id, 'handled_at' => now('UTC'), 'resolution_note' => $data['resolution_note'], 'updated_at' => now('UTC')]);
            $flow->audit($request, $status === 'resolved' ? 'report.resolve' : 'report.dismiss', 'report', $report, ['status' => 'open'], ['status' => $status], $data['resolution_note']);
            app(NotificationOutboxWriter::class)->record('report_'.$status, 'report', $report, "report:{$report}:{$status}", [(int) $row->reporter_id], ['deep_link' => '/reports/'.$report]);

            return response()->json(['data' => $flow->reportData(DB::table('reports')->find($report))]);
        }, 3);
    }

    /** Evidence scoped Report data. */
    #[Response(200, description: 'Authorized result.', type: 'array{data:array{resource_type?:string,resource?:array<string,mixed>,messages?:list<array{id:int,body:?string,created_at:string}>,code?:string,report_id?:int}}')]
    #[Response(401, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(403, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(404, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(409, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(422, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
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

    /** @return array<string,mixed>|null */
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
}
