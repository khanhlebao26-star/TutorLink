<?php

namespace App\Actions\Admin;

use App\Models\User;
use App\Support\TrustWorkflow;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReadAuditLogs
{
    private function scoped(Request $request): Builder
    {
        return DB::table('audit_logs')->where(function ($q) use ($request): void {
            $q->where(fn ($q) => $q->where('resource_type', 'report')->whereIn('resource_id', DB::table('reports')->select('id')->where('assigned_admin_id', $request->user()->id)))
                ->orWhere(fn ($q) => $q->where('resource_type', 'tutor_profile')->whereIn('resource_id', DB::table('tutor_profiles')->select('id')->where('assigned_admin_id', $request->user()->id)));
            if (User::find($request->user()->id)?->hasPermission('admin.reviews.moderate')) {
                $q->orWhere('resource_type', 'review');
            }
        });
    }

    public function index(Request $request, TrustWorkflow $flow): JsonResponse
    {
        $flow->permission($request, 'admin.audit_logs.view');
        $filters = $request->validate(['per_page' => 'sometimes|integer|min:1|max:50', 'resource_type' => 'sometimes|in:report,tutor_profile,review', 'resource_id' => 'sometimes|integer|min:1']);

        return DB::transaction(function () use ($request, $flow, $filters): JsonResponse {
            $flow->lockActor($request);
            $flow->permission($request, 'admin.audit_logs.view');
            $query = $this->scoped($request);
            foreach (['resource_type', 'resource_id'] as $field) {
                if (isset($filters[$field])) {
                    $query->where($field, $filters[$field]);
                }
            }
            $rows = $query->orderByDesc('id')->paginate($filters['per_page'] ?? 20);
            $flow->audit($request, 'audit.read', 'user', $request->user()->id, null, ['returned_count' => $rows->count()]);

            return response()->json(['data' => $rows->getCollection()->map(fn ($row) => $this->data($row, $flow)),
                'meta' => ['current_page' => $rows->currentPage(), 'per_page' => $rows->perPage(), 'total' => $rows->total()]]);
        }, 3);
    }

    public function show(Request $request, int $auditLog, TrustWorkflow $flow): JsonResponse
    {
        $flow->permission($request, 'admin.audit_logs.view');

        return DB::transaction(function () use ($request, $auditLog, $flow): JsonResponse {
            $flow->lockActor($request);
            $flow->permission($request, 'admin.audit_logs.view');
            $row = $this->scoped($request)->where('id', $auditLog)->first();
            abort_unless($row, 404);
            $flow->audit($request, 'audit.read', $row->resource_type, (int) $row->resource_id, null, ['audit_id' => $auditLog]);

            return response()->json(['data' => $this->data($row, $flow)]);
        }, 3);
    }

    private function data(object $row, TrustWorkflow $flow): array
    {
        $before = json_decode($row->before ?? 'null', true);
        $after = json_decode($row->after ?? 'null', true);

        return ['id' => (int) $row->id, 'actor_id' => $row->actor_id === null ? null : (int) $row->actor_id,
            'action' => $row->action, 'resource_type' => $row->resource_type, 'resource_id' => (int) $row->resource_id,
            'before' => $this->projection($before),
            'after' => $this->projection($after),
            'reason' => $row->reason, 'request_id' => $row->request_id, 'created_at' => $flow->timestamp($row->created_at)];
    }

    private function projection(mixed $payload): ?array
    {
        if (! is_array($payload)) {
            return null;
        }
        $result = [];
        $states = ['open', 'resolved', 'dismissed', 'visible', 'hidden', 'draft', 'submitted', 'approved', 'rejected', 'changes_requested', 'suspended', 'active', 'paused', 'completed', 'cancelled'];
        foreach (['status', 'state', 'visibility'] as $key) {
            if (isset($payload[$key]) && is_string($payload[$key]) && in_array($payload[$key], $states, true)) {
                $result[$key] = $payload[$key];
            }
        }
        foreach (['version', 'assignment_version'] as $key) {
            if (isset($payload[$key]) && is_int($payload[$key]) && $payload[$key] >= 0 && $payload[$key] <= 2147483647) {
                $result[$key] = $payload[$key];
            }
        }

        return $result;
    }
}
