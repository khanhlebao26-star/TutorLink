<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TrustWorkflow
{
    public function fail(Request $request, int $status, string $code, string $message): never
    {
        throw new HttpResponseException(ApiErrorResponse::make($status, $code, $message, (string) $request->attributes->get('request_id')));
    }

    public function permission(Request $request, string $permission): void
    {
        $actor = User::find($request->user()->id);
        if (! $actor || ! app(AdminAuthorization::class)->allows($actor, $permission)) {
            $this->fail($request, 403, 'permission_denied', 'Permission denied.');
        }
    }

    public function lockActor(Request $request): User
    {
        $actor = User::whereKey($request->user()->id)->lockForUpdate()->first();
        if (! $actor || $actor->status !== 'active') {
            $this->fail($request, 403, 'account_inactive', 'Account is unavailable.');
        }

        return $actor;
    }

    public function report(Request $request, int $id, string $permission = 'admin.reports.view', bool $lock = false): object
    {
        $this->permission($request, $permission);
        $query = DB::table('reports')->where('id', $id)->where('assigned_admin_id', $request->user()->id);
        $row = ($lock ? $query->lockForUpdate() : $query)->first();
        if (! $row) {
            $this->fail($request, 404, 'report_not_found', 'Report not found.');
        }

        return $row;
    }

    /** @param array<string,mixed>|null $before
     * @param array<string,mixed>|null $after */
    public function audit(Request $request, string $action, string $type, int $id, ?array $before = null, ?array $after = null, ?string $reason = null): void
    {
        DB::table('audit_logs')->insert([
            'actor_id' => $request->user()->id, 'action' => $action, 'resource_type' => $type, 'resource_id' => $id,
            'before' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'after' => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR),
            'reason' => $reason, 'request_id' => $request->attributes->get('request_id'), 'created_at' => now('UTC'),
        ]);
    }

    /** @return array<string,mixed> */
    public function reviewData(object $row): array
    {
        return ['id' => (int) $row->id, 'program_id' => (int) $row->program_id, 'rating' => (int) $row->rating,
            'comment' => $row->comment, 'visibility' => $row->visibility, 'version' => (int) $row->version,
            'created_at' => $this->timestamp($row->created_at)];
    }

    /** @return array<string,mixed> */
    public function reportData(object $row): array
    {
        return ['id' => (int) $row->id, 'resource_type' => $row->resource_type, 'resource_id' => (int) $row->resource_id,
            'reason_code' => $row->reason, 'description' => $row->details, 'status' => $row->status,
            'assigned_admin_id' => $row->assigned_admin_id === null ? null : (int) $row->assigned_admin_id,
            'assignment_version' => (int) $row->assignment_version, 'resolution_note' => $row->resolution_note,
            'handled_at' => $this->timestamp($row->handled_at), 'created_at' => $this->timestamp($row->created_at)];
    }

    public function timestamp(?string $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse($value)->utc()->format('Y-m-d\TH:i:s.u\Z');
    }

    /** @param array<string,mixed> $payload
     * @return array<string,mixed>|null */
    public function replay(Request $request, array $payload): ?array
    {
        $key = $request->header('Idempotency-Key');
        if (! is_string($key) || ! Str::isUuid($key)) {
            $this->fail($request, 422, 'invalid_idempotency_key', 'Idempotency-Key must be a UUID.');
        }
        $row = DB::table('idempotency_receipts')->where('actor_id', $request->user()->id)->where('idempotency_key', strtolower($key))->first();
        if ($row && $row->expires_at !== null && CarbonImmutable::parse($row->expires_at)->lte(now('UTC'))) {
            DB::table('idempotency_receipts')->where('id', $row->id)->delete();
            $row = null;
        }
        if ($row && ($row->method !== $request->method() || $row->canonical_path !== '/'.$request->path() || ! hash_equals($row->request_hash, $this->hash($request, $payload)))) {
            $this->fail($request, 409, 'idempotency_key_reused', 'Idempotency-Key was reused with a different request.');
        }

        return $row ? json_decode($row->response_snapshot, true, 512, JSON_THROW_ON_ERROR) : null;
    }

    /** @param array<string,mixed> $payload */
    public function hash(Request $request, array $payload): string
    {
        ksort($payload);

        return hash('sha256', $request->method()."\n/".$request->path()."\n".json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR));
    }

    /** @param array<string,mixed> $payload
     * @param array<string,mixed> $result */
    public function receipt(Request $request, array $payload, array $result): void
    {
        DB::table('idempotency_receipts')->insert([
            'id' => (string) Str::uuid(), 'actor_id' => $request->user()->id, 'idempotency_key' => strtolower($request->header('Idempotency-Key')),
            'method' => $request->method(), 'canonical_path' => '/'.$request->path(), 'request_hash' => $this->hash($request, $payload),
            'response_status' => 201, 'response_snapshot' => json_encode($result, JSON_THROW_ON_ERROR),
            'created_at' => now('UTC'), 'expires_at' => now('UTC')->addDays(30),
        ]);
    }

    /** @param list<string> $allowed */
    public function fields(Request $request, array $allowed): void
    {
        if (array_diff(array_keys($request->all()), $allowed) !== []) {
            $this->fail($request, 422, 'invalid_fields', 'Unknown request fields.');
        }
    }
}
