<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AdminPermissions;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationOutboxController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission(
            $request,
            AdminPermissions::VIEW_NOTIFICATION_OUTBOX,
        );

        $data = $request->validate([
            'status' => ['sometimes', 'in:pending,failed'],
        ]);

        $status = $data['status'] ?? 'failed';

        $query = DB::table('notification_outbox')
            ->whereNull('processed_at')
            ->whereNull('resolved_at');

        if ($status === 'pending') {
            $query->where(function ($query): void {
                $query->whereNull('failed_at')
                    ->orWhereColumn('replay_requested_at', '>', 'failed_at');
            });
        } else {
            $query->whereNotNull('failed_at')
                ->where(function ($query): void {
                    $query->whereNull('replay_requested_at')
                        ->orWhereColumn('replay_requested_at', '<=', 'failed_at');
                });
        }

        return response()->json(
            $query->select([
                'id',
                'type',
                'resource_type',
                'resource_id',
                'attempt_count',
                'retry_started_at',
                'available_at',
                'failed_at',
                'replay_requested_at',
                'last_error_code',
                'created_at',
            ])
                ->orderBy('available_at')
                ->orderBy('id')
                ->paginate(20),
        );
    }

    public function replay(Request $request, string $outbox): JsonResponse
    {
        $actor = $this->requirePermission(
            $request,
            AdminPermissions::REPLAY_NOTIFICATION_OUTBOX,
        );

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500', 'regex:/\S/u'],
        ]);

        DB::transaction(function () use ($actor, $data, $outbox): void {
            $row = DB::table('notification_outbox')
                ->where('id', $outbox)
                ->lockForUpdate()
                ->first();

            abort_if($row === null, 404, 'Outbox event not found.');

            abort_if(
                $row->processed_at !== null
                || $row->resolved_at !== null
                || $row->failed_at === null,
                409,
                'Only unresolved failed events can be replayed.',
            );

            abort_if(
                $row->replay_requested_at !== null
                && CarbonImmutable::parse($row->replay_requested_at)
                    ->greaterThan(CarbonImmutable::parse($row->failed_at)),
                409,
                'A replay is already pending.',
            );

            $clock = DB::selectOne(
                'SELECT clock_timestamp() AS current_time'
            );

            $requestedAt = CarbonImmutable::parse($clock->current_time);
            $minimum = CarbonImmutable::parse($row->failed_at)
                ->addMicrosecond();

            if ($requestedAt->lessThan($minimum)) {
                $requestedAt = $minimum;
            }

            $timestamp = $requestedAt->format('Y-m-d H:i:s.uP');

            $before = [
                'outbox_id' => $row->id,
                'attempt_count' => (int) $row->attempt_count,
                'retry_started_at' => $row->retry_started_at,
                'available_at' => $row->available_at,
                'failed_at' => $row->failed_at,
                'replay_requested_at' => $row->replay_requested_at,
                'last_error_code' => $row->last_error_code,
            ];

            $changes = [
                'attempt_count' => 0,
                'retry_started_at' => $timestamp,
                'available_at' => $timestamp,
                'replay_requested_at' => $timestamp,
                'last_error_code' => null,
            ];

            DB::table('notification_outbox')
                ->where('id', $row->id)
                ->update($changes);

            DB::table('audit_logs')->insert([
                'actor_id' => $actor->id,
                'action' => 'outbox.replay',
                'resource_type' => $row->resource_type,
                'resource_id' => $row->resource_id,
                'before' => json_encode($before, JSON_THROW_ON_ERROR),
                'after' => json_encode(
                    array_replace($before, $changes),
                    JSON_THROW_ON_ERROR,
                ),
                'reason' => trim($data['reason']),
            ]);
        });

        return response()->json([
            'data' => [
                'id' => $outbox,
                'status' => 'replay_pending',
            ],
        ], 202);
    }

    private function requirePermission(
        Request $request,
        string $permission,
    ): User {
        $actor = $request->user();

        abort_unless(
            $actor instanceof User && $actor->hasPermission($permission),
            403,
            'Permission denied.',
        );

        return $actor;
    }
}
