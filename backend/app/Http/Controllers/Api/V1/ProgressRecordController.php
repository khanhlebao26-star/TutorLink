<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProgressRecordIndexRequest;
use App\Http\Requests\StoreProgressRecordRequest;
use App\Http\Requests\UpdateProgressRecordRequest;
use App\Http\Resources\ProgressRecordResource;
use App\Models\Program;
use App\Models\ProgressRecord;
use App\Models\User;
use App\Support\ApiErrorResponse;
use App\Support\BeD04Workspace;
use Dedoc\Scramble\Attributes\Header as OpenApiHeader;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProgressRecordController extends Controller
{
    private const ERROR_RESPONSE_TYPE = 'array{code: string, message: string, request_id: string, errors: array<string, mixed>}';

    #[OpenApiResponse(status: 200, description: 'Paginated Progress records.', type: 'array{data: list<ProgressRecordResource>, meta: array{current_page: int, per_page: int, total: int, last_page: int}}')]
    #[OpenApiResponse(status: 401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 403, description: 'The account or operation is forbidden.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 404, description: 'Program is not visible to the actor.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 422, description: 'Query parameters are invalid.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 200)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 401)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 403)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 404)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 422)]
    /** List Progress records for a Program. */
    public function index(ProgressRecordIndexRequest $request, int $program): JsonResponse
    {
        $perPage = (int) $request->validated('per_page', 20);
        $page = (int) $request->validated('page', 1);
        $query = ProgressRecord::query()->where('program_id', $program);
        $total = (clone $query)->count();
        $records = $query
            ->orderByRaw('recorded_at DESC NULLS LAST, id DESC')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return response()->json([
            'data' => ProgressRecordResource::collection($records)->resolve($request),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    #[OpenApiResponse(status: 201, description: 'Progress record created.', type: 'array{data: ProgressRecordResource}')]
    #[OpenApiResponse(status: 401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 403, description: 'Only the Program owner may create Progress.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 404, description: 'Program is not visible to the actor.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 409, description: 'Program state does not allow this mutation.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 422, description: 'Request fields are invalid.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 201)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 401)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 403)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 404)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 409)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 422)]
    /** Create a Progress record for a Program. */
    public function store(StoreProgressRecordRequest $request, int $program): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $requestId = (string) $request->attributes->get('request_id');
        $input = $request->validated();

        $record = app(BeD04Workspace::class)->withLockedOwnerProgram(
            $actor,
            $program,
            $requestId,
            function (Program $lockedProgram, User $lockedActor) use ($input, $requestId): ProgressRecord {
                $this->assertWritableProgram($lockedProgram, $requestId);
                $timestamp = now('UTC')->format('Y-m-d H:i:s.uP');
                $id = DB::table('progress_records')->insertGetId([
                    'program_id' => $lockedProgram->id,
                    'customer_id' => $lockedProgram->customer_id,
                    'metric_name' => 'progress_percent',
                    'value' => $input['value'],
                    'note' => $input['note'] ?? null,
                    'recorded_by' => $lockedActor->id,
                    'recorded_at' => $timestamp,
                    'version' => 1,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);

                return ProgressRecord::query()->findOrFail($id);
            },
        );

        return response()->json(['data' => (new ProgressRecordResource($record))->resolve($request)], 201);
    }

    #[OpenApiResponse(status: 200, description: 'Progress record updated.', type: 'array{data: ProgressRecordResource}')]
    #[OpenApiResponse(status: 401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 403, description: 'Only the Program owner may update Progress.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 404, description: 'Progress record is not visible to the actor.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 409, description: 'Version or Program state conflicts with the request.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 422, description: 'Request fields are invalid.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 200)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 401)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 403)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 404)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 409)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 422)]
    /** Update a Progress record. */
    public function update(UpdateProgressRecordRequest $request, int $progressRecord): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $requestId = (string) $request->attributes->get('request_id');
        $programId = (int) $request->attributes->get('d04_program_id');
        $input = $request->validated();

        $record = app(BeD04Workspace::class)->withLockedOwnerProgram(
            $actor,
            $programId,
            $requestId,
            function (Program $lockedProgram, User $lockedActor) use ($input, $progressRecord, $requestId): ProgressRecord {
                $this->assertWritableProgram($lockedProgram, $requestId);
                $record = ProgressRecord::query()->whereKey($progressRecord)->lockForUpdate()->first();

                if ($record === null || (int) $record->program_id !== (int) $lockedProgram->id) {
                    $this->fail(404, 'progress_record_not_found', 'Progress record not found.', $requestId);
                }

                if ((int) $record->version !== (int) $input['expected_version']) {
                    throw new HttpResponseException(ApiErrorResponse::staleVersion((int) $record->version, $requestId));
                }

                $beforeValue = (int) $record->value;
                $beforeNote = $record->note;
                $newValue = array_key_exists('value', $input) ? (int) $input['value'] : $beforeValue;
                $newNote = array_key_exists('note', $input) ? $input['note'] : $beforeNote;

                if ($newValue === $beforeValue && $newNote === $beforeNote) {
                    return $record;
                }

                $newVersion = (int) $record->version + 1;
                $timestamp = now('UTC')->format('Y-m-d H:i:s.uP');
                $before = $this->auditProjection($record, $beforeValue, $beforeNote);
                $after = $this->auditProjection($record, $newValue, $newNote, $newVersion);

                DB::table('progress_records')->where('id', $record->id)->update([
                    'value' => $newValue,
                    'note' => $newNote,
                    'version' => $newVersion,
                    'updated_at' => $timestamp,
                ]);
                DB::table('audit_logs')->insert([
                    'actor_id' => $lockedActor->id,
                    'action' => 'progress_record.update',
                    'resource_type' => 'progress_record',
                    'resource_id' => $record->id,
                    'before' => json_encode($before, JSON_THROW_ON_ERROR),
                    'after' => json_encode($after, JSON_THROW_ON_ERROR),
                    'reason' => null,
                    'request_id' => $requestId,
                    'created_at' => $timestamp,
                ]);

                return ProgressRecord::query()->findOrFail($record->id);
            },
        );

        return response()->json(['data' => (new ProgressRecordResource($record))->resolve($request)]);
    }

    private function assertWritableProgram(Program $program, string $requestId): void
    {
        if (! in_array($program->status, ['active', 'paused'], true)) {
            $this->fail(409, 'program_state_conflict', 'Progress cannot be changed for this Program state.', $requestId);
        }
    }

    /** @return array<string, mixed> */
    private function auditProjection(ProgressRecord $record, int $value, ?string $note, ?int $version = null): array
    {
        return [
            'program_id' => (int) $record->program_id,
            'metric_name' => $record->metric_name,
            'value' => $value,
            'version' => $version ?? (int) $record->version,
            'note_present' => $note !== null,
            'note_length' => $note === null ? null : mb_strlen($note, 'UTF-8'),
            'note_sha256' => $note === null ? null : hash('sha256', $note),
        ];
    }

    private function fail(int $status, string $code, string $message, string $requestId): never
    {
        throw new HttpResponseException(ApiErrorResponse::make($status, $code, $message, $requestId));
    }
}
