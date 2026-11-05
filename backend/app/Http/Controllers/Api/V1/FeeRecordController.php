<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\FeeRecordIndexRequest;
use App\Http\Requests\FeeRecordShowRequest;
use App\Http\Requests\MarkFeeRecordPaidRequest;
use App\Http\Requests\MarkFeeRecordUnpaidRequest;
use App\Http\Requests\StoreFeeRecordRequest;
use App\Http\Requests\UpdateFeeRecordRequest;
use App\Http\Requests\VoidFeeRecordRequest;
use App\Http\Resources\FeeRecordResource;
use App\Models\FeeRecord;
use App\Models\Program;
use App\Models\User;
use App\Support\ApiErrorResponse;
use App\Support\BeD04Workspace;
use App\Support\NotificationOutboxWriter;
use Carbon\CarbonImmutable;
use Closure;
use Dedoc\Scramble\Attributes\Header as OpenApiHeader;
use Dedoc\Scramble\Attributes\HeaderParameter as OpenApiHeaderParameter;
use Dedoc\Scramble\Attributes\IgnoreResponse as OpenApiIgnoreResponse;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FeeRecordController extends Controller
{
    private const ERROR_RESPONSE_TYPE = 'array{code: string, message: string, request_id: string, errors: array<string, mixed>}';

    private const FEE_RECORD_TYPE = "array{id:int,program_id:int,period:string,period_start:string,period_end:string,amount_minor:int,currency:'VND',status:'declared'|'voided',tutor_payment_mark:'unmarked'|'marked_paid'|'marked_unpaid',version:int,payment_marked_at:string|null,payment_mark_reason:string|null,voided_at:string|null,void_reason:string|null,last_change_reason:string|null,disclaimer:string,created_at:string|null,updated_at:string|null}";

    #[OpenApiResponse(status: 200, description: 'Paginated Fee records with independent Fee state and payment mark.', type: 'array{data: list<'.self::FEE_RECORD_TYPE.'>, meta: array{current_page: int, per_page: int, total: int, last_page: int}}')]
    #[OpenApiResponse(status: 401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 403, description: 'The account or operation is forbidden.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 404, description: 'Program is not visible to the actor.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 422, description: 'Filter or pagination parameters are invalid.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 200)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 401)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 403)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 404)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 422)]
    /** List Fee records for a Program. */
    public function index(FeeRecordIndexRequest $request, int $program): JsonResponse
    {
        $page = (int) $request->validated('page', 1);
        $perPage = (int) $request->validated('per_page', 20);
        $query = FeeRecord::query()->where('program_id', (int) $request->attributes->get('d04_program_id'));

        if ($request->validated('period') !== null) {
            $query->where('period_start', $request->validated('period').'-01');
        }

        if ($request->validated('status') !== null) {
            $query->where('status', $request->validated('status'));
        }

        if ($request->validated('tutor_payment_mark') !== null) {
            $query->where('tutor_payment_mark', $request->validated('tutor_payment_mark'));
        }

        $total = (clone $query)->count();
        $records = $query
            ->orderByDesc('period_start')
            ->orderByDesc('id')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return response()->json([
            'data' => FeeRecordResource::collection($records)->resolve($request),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    #[OpenApiResponse(status: 200, description: 'Fee record resource.', type: 'array{data:'.self::FEE_RECORD_TYPE.'}')]
    #[OpenApiResponse(status: 401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 403, description: 'The account is forbidden.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 404, description: 'Fee record is not visible to the actor.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 422, description: 'Request parameters are invalid.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 200)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 401)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 403)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 404)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 422)]
    /** Show a Fee record. */
    public function show(FeeRecordShowRequest $request, int $feeRecord): JsonResponse
    {
        $fee = FeeRecord::query()->findOrFail($feeRecord);

        return response()->json(['data' => (new FeeRecordResource($fee))->resolve($request)]);
    }

    #[OpenApiIgnoreResponse(200)]
    #[OpenApiResponse(status: 201, description: 'Fee record created.', type: 'array{data:'.self::FEE_RECORD_TYPE.'}')]
    #[OpenApiResponse(status: 401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 403, description: 'Only the Program owner may create Fee records.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 404, description: 'Program is not visible to the actor.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 409, description: 'Program state or monthly Fee uniqueness conflicts with the request.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 422, description: 'Request fields or idempotency key are invalid.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiHeaderParameter('Idempotency-Key', description: 'Required UUID key for Fee write replay.', type: 'string', format: 'uuid', required: true)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 201)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 401)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 403)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 404)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 409)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 422)]
    /** Create a Fee record. */
    public function store(StoreFeeRecordRequest $request, int $program): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $input = $request->validated();
        $programId = (int) $request->attributes->get('d04_program_id');
        $path = "/api/v1/programs/{$programId}/fee-records";
        $key = (string) $request->attributes->get('d04_idempotency_key');
        $hash = $this->requestHash('POST', $path, [
            'period' => $input['period'],
            'amount_minor' => (int) $input['amount_minor'],
            'currency' => $input['currency'] ?? 'VND',
        ]);

        return $this->write(
            $request,
            $actor,
            $programId,
            null,
            true,
            'POST',
            $path,
            $key,
            $hash,
            function (Program $lockedProgram, User $lockedActor, ?object $member, ?FeeRecord $fee) use ($input, $request): array {
                [$periodStart, $periodEnd] = $this->periodBounds($lockedProgram, $input['period'], (string) $request->attributes->get('request_id'));

                if (FeeRecord::query()->where('program_id', $lockedProgram->id)->where('period_start', $periodStart)->exists()) {
                    $this->fail(409, 'fee_period_exists', 'A Fee record already exists for this Program and month.', (string) $request->attributes->get('request_id'));
                }

                $timestamp = now('UTC')->format('Y-m-d H:i:s.uP');
                $id = DB::table('fee_records')->insertGetId([
                    'program_id' => $lockedProgram->id,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'amount_minor' => (int) $input['amount_minor'],
                    'currency' => $input['currency'] ?? 'VND',
                    'status' => 'declared',
                    'tutor_payment_mark' => 'unmarked',
                    'version' => 1,
                    'payment_marked_at' => null,
                    'payment_marked_by' => null,
                    'payment_mark_reason' => null,
                    'voided_at' => null,
                    'voided_by' => null,
                    'void_reason' => null,
                    'last_change_reason' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
                $fee = FeeRecord::query()->findOrFail($id);
                $this->audit($lockedActor, $fee, 'fee_record.create', null, $this->auditProjection($fee), null, (string) $request->attributes->get('request_id'), $timestamp);
                $this->notify($lockedProgram, $member, $fee);

                return ['status' => 201, 'fee' => $fee];
            },
        );
    }

    #[OpenApiResponse(status: 200, description: 'Fee record updated.', type: 'array{data:'.self::FEE_RECORD_TYPE.'}')]
    #[OpenApiResponse(status: 401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 403, description: 'Only the Program owner may update Fee records.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 404, description: 'Fee record is not visible to the actor.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 409, description: 'Version, Fee state, or Program state conflicts with the request.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 422, description: 'Request fields or idempotency key are invalid.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiHeaderParameter('Idempotency-Key', description: 'Required UUID key for Fee write replay.', type: 'string', format: 'uuid', required: true)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 200)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 401)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 403)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 404)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 409)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 422)]
    /** Update a Fee record. */
    public function update(UpdateFeeRecordRequest $request, int $feeRecord): JsonResponse
    {
        $input = $request->validated();

        return $this->mutateExisting(
            $request,
            $feeRecord,
            'PATCH',
            ['amount_minor' => (int) $input['amount_minor'], 'expected_version' => (int) $input['expected_version'], 'reason' => $input['reason']],
            function (Program $program, User $actor, ?object $member, FeeRecord $fee) use ($input, $request): array {
                $this->assertVersion($fee, (int) $input['expected_version'], (string) $request->attributes->get('request_id'));
                $this->assertDeclaredFee($fee, (string) $request->attributes->get('request_id'));

                if ($fee->tutor_payment_mark === 'marked_paid') {
                    $this->stateConflict($request, 'Amount cannot be changed after the Tutor marks it paid.');
                }

                if ((int) $fee->amount_minor === (int) $input['amount_minor']) {
                    return ['status' => 200, 'fee' => $fee];
                }

                if ((int) $input['amount_minor'] === 0 && $fee->tutor_payment_mark !== 'unmarked') {
                    $this->stateConflict($request, 'A marked Fee cannot be changed to zero.');
                }

                $before = $this->auditProjection($fee);
                $version = (int) $fee->version + 1;
                $timestamp = now('UTC')->format('Y-m-d H:i:s.uP');
                DB::table('fee_records')->where('id', $fee->id)->update([
                    'amount_minor' => (int) $input['amount_minor'],
                    'last_change_reason' => $input['reason'],
                    'version' => $version,
                    'updated_at' => $timestamp,
                ]);
                $fee = FeeRecord::query()->findOrFail($fee->id);
                $this->audit($actor, $fee, 'fee_record.update', $before, $this->auditProjection($fee), $input['reason'], (string) $request->attributes->get('request_id'), $timestamp);
                $this->notify($program, $member, $fee);

                return ['status' => 200, 'fee' => $fee];
            },
        );
    }

    #[OpenApiResponse(status: 200, description: 'Fee payment mark updated to paid.', type: 'array{data:'.self::FEE_RECORD_TYPE.'}')]
    #[OpenApiResponse(status: 401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 403, description: 'Only the Program owner may mark Fee records.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 404, description: 'Fee record is not visible to the actor.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 409, description: 'Version, Fee state, or Program state conflicts with the request.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 422, description: 'Request fields or idempotency key are invalid.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiHeaderParameter('Idempotency-Key', description: 'Required UUID key for Fee write replay.', type: 'string', format: 'uuid', required: true)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 200)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 401)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 403)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 404)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 409)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 422)]
    /** Mark a Fee record as paid. */
    public function markPaid(MarkFeeRecordPaidRequest $request, int $feeRecord): JsonResponse
    {
        $input = $request->validated();

        return $this->mutateExisting(
            $request,
            $feeRecord,
            'POST',
            ['expected_version' => (int) $input['expected_version'], 'reason' => $input['reason'] ?? null],
            function (Program $program, User $actor, ?object $member, FeeRecord $fee) use ($input, $request): array {
                $this->assertVersion($fee, (int) $input['expected_version'], (string) $request->attributes->get('request_id'));
                $this->assertDeclaredFee($fee, (string) $request->attributes->get('request_id'));

                if ((int) $fee->amount_minor === 0) {
                    $this->stateConflict($request, 'A zero amount Fee cannot be marked paid or unpaid.');
                }

                if ($fee->tutor_payment_mark === 'marked_paid') {
                    return ['status' => 200, 'fee' => $fee];
                }

                return $this->changeMark($program, $actor, $member, $fee, 'marked_paid', $input['reason'] ?? null, $request);
            },
        );
    }

    #[OpenApiResponse(status: 200, description: 'Fee payment mark updated to unpaid.', type: 'array{data:'.self::FEE_RECORD_TYPE.'}')]
    #[OpenApiResponse(status: 401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 403, description: 'Only the Program owner may mark Fee records.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 404, description: 'Fee record is not visible to the actor.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 409, description: 'Version, Fee state, or Program state conflicts with the request.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 422, description: 'Request fields or idempotency key are invalid.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiHeaderParameter('Idempotency-Key', description: 'Required UUID key for Fee write replay.', type: 'string', format: 'uuid', required: true)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 200)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 401)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 403)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 404)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 409)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 422)]
    /** Mark a Fee record as unpaid. */
    public function markUnpaid(MarkFeeRecordUnpaidRequest $request, int $feeRecord): JsonResponse
    {
        $input = $request->validated();

        return $this->mutateExisting(
            $request,
            $feeRecord,
            'POST',
            ['expected_version' => (int) $input['expected_version'], 'reason' => $input['reason'] ?? null],
            function (Program $program, User $actor, ?object $member, FeeRecord $fee) use ($input, $request): array {
                $this->assertVersion($fee, (int) $input['expected_version'], (string) $request->attributes->get('request_id'));
                $this->assertDeclaredFee($fee, (string) $request->attributes->get('request_id'));

                if ((int) $fee->amount_minor === 0) {
                    $this->stateConflict($request, 'A zero amount Fee cannot be marked paid or unpaid.');
                }

                if ($fee->tutor_payment_mark === 'marked_unpaid') {
                    return ['status' => 200, 'fee' => $fee];
                }

                $reason = $input['reason'] ?? null;
                if ($fee->tutor_payment_mark === 'marked_paid' && $reason === null) {
                    throw new HttpResponseException(ApiErrorResponse::make(
                        422,
                        'validation_failed',
                        'The given data was invalid.',
                        (string) $request->attributes->get('request_id'),
                        ['reason' => ['A reason is required when changing marked_paid to marked_unpaid.']],
                    ));
                }

                return $this->changeMark($program, $actor, $member, $fee, 'marked_unpaid', $reason, $request);
            },
        );
    }

    #[OpenApiResponse(status: 200, description: 'Fee record voided.', type: 'array{data:'.self::FEE_RECORD_TYPE.'}')]
    #[OpenApiResponse(status: 401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 403, description: 'Only the Program owner may void Fee records.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 404, description: 'Fee record is not visible to the actor.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 409, description: 'Version, Fee state, or Program state conflicts with the request.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 422, description: 'Request fields or idempotency key are invalid.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiHeaderParameter('Idempotency-Key', description: 'Required UUID key for Fee write replay.', type: 'string', format: 'uuid', required: true)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 200)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 401)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 403)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 404)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 409)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 422)]
    /** Void a Fee record. */
    public function void(VoidFeeRecordRequest $request, int $feeRecord): JsonResponse
    {
        $input = $request->validated();

        return $this->mutateExisting(
            $request,
            $feeRecord,
            'POST',
            ['expected_version' => (int) $input['expected_version'], 'reason' => $input['reason']],
            function (Program $program, User $actor, ?object $member, FeeRecord $fee) use ($input, $request): array {
                $this->assertVersion($fee, (int) $input['expected_version'], (string) $request->attributes->get('request_id'));
                $this->assertDeclaredFee($fee, (string) $request->attributes->get('request_id'));
                $before = $this->auditProjection($fee);
                $version = (int) $fee->version + 1;
                $timestamp = now('UTC')->format('Y-m-d H:i:s.uP');
                DB::table('fee_records')->where('id', $fee->id)->update([
                    'status' => 'voided',
                    'voided_at' => $timestamp,
                    'voided_by' => $actor->id,
                    'void_reason' => $input['reason'],
                    'last_change_reason' => $input['reason'],
                    'version' => $version,
                    'updated_at' => $timestamp,
                ]);
                $fee = FeeRecord::query()->findOrFail($fee->id);
                $this->audit($actor, $fee, 'fee_record.void', $before, $this->auditProjection($fee), $input['reason'], (string) $request->attributes->get('request_id'), $timestamp);
                $this->notify($program, $member, $fee);

                return ['status' => 200, 'fee' => $fee];
            },
        );
    }

    private function mutateExisting(object $request, int $feeRecord, string $method, array $payload, Closure $mutation): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $programId = (int) $request->attributes->get('d04_program_id');
        $path = match ($method) {
            'PATCH' => "/api/v1/fee-records/{$feeRecord}",
            default => "/api/v1/fee-records/{$feeRecord}/".match (true) {
                str_contains($request->path(), '/mark-paid') => 'mark-paid',
                str_contains($request->path(), '/mark-unpaid') => 'mark-unpaid',
                default => 'void',
            },
        };

        return $this->write(
            $request,
            $actor,
            $programId,
            $feeRecord,
            false,
            $method,
            $path,
            (string) $request->attributes->get('d04_idempotency_key'),
            $this->requestHash($method, $path, $payload),
            fn (Program $program, User $lockedActor, ?object $member, ?FeeRecord $fee): array => $mutation($program, $lockedActor, $member, $fee),
        );
    }

    private function write(
        object $request,
        User $actor,
        int $programId,
        ?int $feeRecordId,
        bool $creating,
        string $method,
        string $path,
        string $key,
        string $hash,
        Closure $mutation,
    ): JsonResponse {
        $requestId = (string) $request->attributes->get('request_id');
        $result = app(BeD04Workspace::class)->withLockedOwnerProgram(
            $actor,
            $programId,
            $requestId,
            function (Program $program, User $lockedActor, ?object $member) use ($request, $feeRecordId, $creating, $method, $path, $key, $hash, $mutation, $requestId): array {
                $fee = null;
                if ($feeRecordId !== null) {
                    $fee = FeeRecord::query()->whereKey($feeRecordId)->lockForUpdate()->first();

                    if ($fee === null || (int) $fee->program_id !== (int) $program->id) {
                        $this->fail(404, 'fee_record_not_found', 'Fee record not found.', $requestId);
                    }
                }

                $replay = $this->replay($lockedActor, $key, $method, $path, $hash, $requestId);
                if ($replay !== null) {
                    return $replay;
                }

                if ($creating) {
                    if ($program->status !== 'active') {
                        $this->fail(409, 'program_state_conflict', 'A Fee record can only be created for an Active Program.', $requestId);
                    }
                } elseif (! in_array($program->status, ['active', 'paused'], true)) {
                    $this->fail(409, 'program_state_conflict', 'Fee records cannot be changed for this Program state.', $requestId);
                }

                $result = $mutation($program, $lockedActor, $member, $fee);
                $body = ['data' => (new FeeRecordResource($result['fee']))->resolve($request)];
                DB::table('idempotency_receipts')->insert([
                    'id' => (string) Str::uuid(),
                    'actor_id' => $lockedActor->id,
                    'idempotency_key' => $key,
                    'method' => $method,
                    'canonical_path' => $path,
                    'request_hash' => $hash,
                    'response_status' => $result['status'],
                    'response_snapshot' => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR),
                    'created_at' => now('UTC')->format('Y-m-d H:i:s.uP'),
                    'expires_at' => null,
                ]);

                return ['status' => $result['status'], 'body' => $body];
            },
        );

        return response()->json($result['body'], $result['status']);
    }

    private function replay(User $actor, string $key, string $method, string $path, string $hash, string $requestId): ?array
    {
        $receipt = DB::table('idempotency_receipts')
            ->where('actor_id', $actor->id)
            ->where('idempotency_key', $key)
            ->lockForUpdate()
            ->first();

        if ($receipt === null) {
            return null;
        }

        $now = CarbonImmutable::now('UTC');
        if ($receipt->expires_at !== null && CarbonImmutable::parse($receipt->expires_at)->lessThanOrEqualTo($now)) {
            DB::table('idempotency_receipts')->where('id', $receipt->id)->delete();

            return null;
        }

        if ($receipt->method !== $method || $receipt->canonical_path !== $path || ! hash_equals($receipt->request_hash, $hash)) {
            $this->fail(409, 'idempotency_conflict', 'Idempotency-Key was used for another request.', $requestId);
        }

        $body = json_decode($receipt->response_snapshot, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($body)) {
            throw new \LogicException('Stored Fee response is invalid.');
        }

        return ['status' => (int) $receipt->response_status, 'body' => $body];
    }

    private function requestHash(string $method, string $path, array $payload): string
    {
        ksort($payload, SORT_STRING);
        $canonical = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR);

        return hash('sha256', "{$method}\n{$path}\n{$canonical}");
    }

    /** @return array{0: string, 1: string} */
    private function periodBounds(Program $program, string $period, string $requestId): array
    {
        if (! FeeRecordIndexRequest::validPeriod($period)) {
            $this->validationError('period', 'The period must be a valid calendar month in YYYY-MM format.', $requestId);
        }

        $timezone = new \DateTimeZone($program->timezone);
        $requestedMonth = CarbonImmutable::createFromFormat('!Y-m-d', $period.'-01', $timezone);
        $activationMonth = CarbonImmutable::parse($program->activated_at)->setTimezone($timezone)->startOfMonth();
        $currentMonth = CarbonImmutable::now($timezone)->startOfMonth();

        if ($requestedMonth->lt($activationMonth) || $requestedMonth->gt($currentMonth)) {
            $this->validationError('period', 'The period must be between the Program activation month and current month in its timezone.', $requestId);
        }

        return [$requestedMonth->toDateString(), $requestedMonth->endOfMonth()->toDateString()];
    }

    private function assertVersion(FeeRecord $fee, int $expectedVersion, string $requestId): void
    {
        if ((int) $fee->version !== $expectedVersion) {
            throw new HttpResponseException(ApiErrorResponse::staleVersion((int) $fee->version, $requestId));
        }
    }

    private function assertDeclaredFee(FeeRecord $fee, string $requestId): void
    {
        if ($fee->status !== 'declared') {
            $this->fail(409, 'fee_record_state_conflict', 'Voided Fee records are immutable.', $requestId);
        }
    }

    private function changeMark(Program $program, User $actor, ?object $member, FeeRecord $fee, string $mark, ?string $reason, object $request): array
    {
        $before = $this->auditProjection($fee);
        $version = (int) $fee->version + 1;
        $timestamp = now('UTC')->format('Y-m-d H:i:s.uP');
        DB::table('fee_records')->where('id', $fee->id)->update([
            'tutor_payment_mark' => $mark,
            'payment_marked_at' => $timestamp,
            'payment_marked_by' => $actor->id,
            'payment_mark_reason' => $reason,
            'last_change_reason' => $reason,
            'version' => $version,
            'updated_at' => $timestamp,
        ]);
        $fee = FeeRecord::query()->findOrFail($fee->id);
        $this->audit($actor, $fee, 'fee_record.mark', $before, $this->auditProjection($fee), $reason, (string) $request->attributes->get('request_id'), $timestamp);
        $this->notify($program, $member, $fee);

        return ['status' => 200, 'fee' => $fee];
    }

    private function notify(Program $program, ?object $member, FeeRecord $fee): void
    {
        $recipientIds = [];
        if ($member !== null && $member->status === 'active') {
            $customer = User::query()->whereKey($member->user_id)->first();
            if ($customer !== null && $customer->status === 'active' && $customer->deleted_at === null) {
                $recipientIds[] = (int) $customer->id;
            }
        }

        app(NotificationOutboxWriter::class)->record(
            'fee_record_changed',
            'fee_record',
            (int) $fee->id,
            "fee_record:{$fee->id}:v{$fee->version}",
            $recipientIds,
            [
                'deep_link' => "/programs/{$program->id}?tab=fees&fee_record_id={$fee->id}",
                'period' => substr((string) $fee->period_start, 0, 7),
            ],
        );
    }

    /** @return array<string, mixed> */
    private function auditProjection(FeeRecord $fee): array
    {
        return [
            'program_id' => (int) $fee->program_id,
            'period_start' => (string) $fee->period_start,
            'period_end' => (string) $fee->period_end,
            'amount_minor' => (int) $fee->amount_minor,
            'currency' => $fee->currency,
            'status' => $fee->status,
            'tutor_payment_mark' => $fee->tutor_payment_mark,
            'version' => (int) $fee->version,
            'voided_at' => $this->iso($fee->voided_at),
            'voided_by' => $fee->voided_by === null ? null : (int) $fee->voided_by,
            'void_reason' => $fee->void_reason,
            'payment_marked_at' => $this->iso($fee->payment_marked_at),
            'payment_marked_by' => $fee->payment_marked_by === null ? null : (int) $fee->payment_marked_by,
            'payment_mark_reason' => $fee->payment_mark_reason,
            'last_change_reason' => $fee->last_change_reason,
        ];
    }

    private function audit(User $actor, FeeRecord $fee, string $action, ?array $before, array $after, ?string $reason, string $requestId, string $timestamp): void
    {
        DB::table('audit_logs')->insert([
            'actor_id' => $actor->id,
            'action' => $action,
            'resource_type' => 'fee_record',
            'resource_id' => $fee->id,
            'before' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'after' => json_encode($after, JSON_THROW_ON_ERROR),
            'reason' => $reason,
            'request_id' => $requestId,
            'created_at' => $timestamp,
        ]);
    }

    private function iso(mixed $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse($value)->utc()->format('Y-m-d\TH:i:s.u\Z');
    }

    private function stateConflict(object $request, string $message): never
    {
        $this->fail(409, 'fee_record_state_conflict', $message, (string) $request->attributes->get('request_id'));
    }

    private function validationError(string $field, string $message, string $requestId): never
    {
        throw new HttpResponseException(ApiErrorResponse::make(422, 'validation_failed', 'The given data was invalid.', $requestId, [$field => [$message]]));
    }

    private function fail(int $status, string $code, string $message, string $requestId): never
    {
        throw new HttpResponseException(ApiErrorResponse::make($status, $code, $message, $requestId));
    }
}
