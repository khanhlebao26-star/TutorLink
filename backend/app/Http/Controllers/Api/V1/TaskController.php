<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Support\ApiErrorResponse;
use App\Support\TaskSubmissionRequestHash;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

class TaskController extends Controller
{
    private const ERROR_RESPONSE_TYPE = 'array{code:string,message:string,request_id:string,errors?:array<string,list<string>>}';

    private const TASK_TYPE = 'array{id:int,program_id:int,title:string,description:string|null,due_at:string|null,assigned_to:int|null,status:string,version:int,current_submission_id:int|null,file_ids:list<int>}';

    private const SUBMISSION_TYPE = 'array{id:int,task_id:int,submission_version:int,content:string|null,status:string,submitted_at:string,feedback:string|null,reviewed_by:int|null,reviewed_at:string|null,file_ids:list<int>}';

    private const FEEDBACK_TYPE = 'array{id:int,task_id:int,submission_version:int,version:int,status:string,feedback:string|null,reviewed_by:int|null,reviewed_at:string|null}';

    #[OpenApiResponse(200, description: 'Tasks ordered by ascending id for the visible Program.', type: 'array{data:list<'.self::TASK_TYPE.'>,meta:array{current_page:int,per_page:int,total:int,last_page:int}}')]
    #[OpenApiResponse(401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(403, description: 'The actor account is inactive.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(404, description: 'Program does not exist or is outside the actor scope.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(422, description: 'Pagination query is invalid.', type: self::ERROR_RESPONSE_TYPE)]
    #[QueryParameter('page', 'One-based page number.', type: 'integer', default: 1)]
    #[QueryParameter('per_page', 'Page size: 1–50.', type: 'integer', default: 20)]
    public function index(Request $request, int $program): JsonResponse
    {
        $page = $this->pagination($request);
        $record = DB::table('programs')->find($program);
        abort_unless($record && $this->role($record, $request), 404);

        $tasks = DB::table('tasks')->where('program_id', $program)
            ->orderBy('id')->paginate($page['per_page'], ['*'], 'page', $page['page']);

        return response()->json($this->paginated($tasks, fn ($task) => $this->taskData($task)));
    }

    #[OpenApiResponse(201, description: 'Task created with version 1.', type: 'array{data:'.self::TASK_TYPE.'}')]
    #[OpenApiResponse(401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(403, description: 'Only the Program owner may create Tasks.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(404, description: 'Program does not exist.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(409, description: 'Program state does not allow Task creation.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(422, description: 'Task fields, assignee membership, or attachments are invalid.', type: self::ERROR_RESPONSE_TYPE)]
    public function store(Request $request, int $program): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:180',
            'description' => 'nullable|string',
            'due_at' => 'nullable|date',
            'assigned_to' => 'required|integer',
            'file_ids' => 'sometimes|array|max:5',
            'file_ids.*' => 'required|integer|distinct',
        ]);

        return DB::transaction(function () use ($request, $program, $data) {
            $record = DB::table('programs')->where('id', $program)->lockForUpdate()->first();
            abort_unless($record, 404);
            abort_unless($this->role($record, $request) === 'owner', 403);
            if ($record->status !== 'active') {
                return $this->error($request, 409, 'program_state_conflict');
            }
            abort_unless($this->member($program, (int) $data['assigned_to']), 422);
            $files = $this->files($request, $data['file_ids'] ?? []);
            $now = now('UTC');
            $id = DB::table('tasks')->insertGetId([
                'program_id' => $program,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'due_at' => $data['due_at'] ?? null,
                'assigned_to' => $data['assigned_to'],
                'status' => 'todo',
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->linkFiles($files, 'task', $id);

            return response()->json(['data' => $this->taskData(DB::table('tasks')->find($id))], 201);
        });
    }

    #[OpenApiResponse(200, description: 'Task fields, current submission id, and linked file ids.', type: 'array{data:'.self::TASK_TYPE.'}')]
    #[OpenApiResponse(401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(403, description: 'The actor account is inactive.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(404, description: 'Task does not exist or is outside the actor scope.', type: self::ERROR_RESPONSE_TYPE)]
    public function show(Request $request, int $task): JsonResponse
    {
        [$record, $program] = $this->visibleTask($request, $task);

        return response()->json(['data' => $this->taskData($record)]);
    }

    #[OpenApiResponse(200, description: 'Updated Task resource and incremented version.', type: 'array{data:'.self::TASK_TYPE.'}')]
    #[OpenApiResponse(401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(403, description: 'Only the Program owner may edit this Task.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(404, description: 'Task does not exist.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(409, description: 'Task version is stale or Task/Program state disallows editing.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(422, description: 'Expected version, fields, or attachments are invalid.', type: self::ERROR_RESPONSE_TYPE)]
    public function update(Request $request, int $task): JsonResponse
    {
        $data = $request->validate([
            'expected_version' => 'required|integer|min:1',
            'title' => 'sometimes|required|string|max:180',
            'description' => 'sometimes|nullable|string',
            'due_at' => 'sometimes|nullable|date',
            'file_ids' => 'sometimes|array|max:5',
            'file_ids.*' => 'required|integer|distinct',
        ]);

        return $this->mutateTask($request, $task, 'owner', $data, function ($record, $program) use ($request, $data) {
            if ($program->status !== 'active' || $record->status !== 'todo') {
                return $this->error($request, 409, 'task_state_conflict');
            }
            $changes = array_intersect_key($data, array_flip(['title', 'description', 'due_at']));
            if (array_key_exists('file_ids', $data)) {
                $files = $this->files($request, $data['file_ids']);
                DB::table('file_links')->where('resource_type', 'task')->where('resource_id', $record->id)->delete();
                $this->linkFiles($files, 'task', $record->id);
            }
            DB::table('tasks')->where('id', $record->id)->update([...$changes, 'version' => $record->version + 1, 'updated_at' => now('UTC')]);

            return response()->json(['data' => $this->taskData(DB::table('tasks')->find($record->id))]);
        });
    }

    #[OpenApiResponse(200, description: 'Task started with the incremented version.', type: 'array{data:'.self::TASK_TYPE.'}')]
    #[OpenApiResponse(401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(403, description: 'Only the assigned active Customer may start this Task.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(404, description: 'Task does not exist.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(409, description: 'Task version is stale or Task/Program state disallows starting.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(422, description: 'Expected version is invalid.', type: self::ERROR_RESPONSE_TYPE)]
    public function start(Request $request, int $task): JsonResponse
    {
        $data = $request->validate(['expected_version' => 'required|integer|min:1']);

        return $this->mutateTask($request, $task, 'assignee', $data, function ($record, $program) use ($request) {
            if (! in_array($program->status, ['active', 'paused'], true) || $record->status !== 'todo') {
                return $this->error($request, 409, 'task_state_conflict');
            }
            DB::table('tasks')->where('id', $record->id)->update(['status' => 'in_progress', 'version' => $record->version + 1, 'updated_at' => now('UTC')]);

            return response()->json(['data' => $this->taskData(DB::table('tasks')->find($record->id))]);
        });
    }

    #[OpenApiResponse(200, description: 'Task cancelled with the incremented version.', type: 'array{data:'.self::TASK_TYPE.'}')]
    #[OpenApiResponse(401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(403, description: 'Only the Program owner may cancel this Task.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(404, description: 'Task does not exist.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(409, description: 'Task version is stale or Task/Program state disallows cancellation.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(422, description: 'Expected version is invalid.', type: self::ERROR_RESPONSE_TYPE)]
    public function cancel(Request $request, int $task): JsonResponse
    {
        $data = $request->validate(['expected_version' => 'required|integer|min:1']);

        return $this->mutateTask($request, $task, 'owner', $data, function ($record, $program) use ($request) {
            if (! in_array($program->status, ['active', 'paused'], true) || ! in_array($record->status, ['todo', 'in_progress'], true)) {
                return $this->error($request, 409, 'task_state_conflict');
            }
            DB::table('tasks')->where('id', $record->id)->update(['status' => 'cancelled', 'version' => $record->version + 1, 'updated_at' => now('UTC')]);

            return response()->json(['data' => $this->taskData(DB::table('tasks')->find($record->id))]);
        });
    }

    #[OpenApiResponse(200, description: 'Current Submission resource, or null when none exists.', type: 'array{data:'.self::SUBMISSION_TYPE.'|null}')]
    #[OpenApiResponse(401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(403, description: 'The actor account is inactive.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(404, description: 'Task does not exist or is outside the actor scope.', type: self::ERROR_RESPONSE_TYPE)]
    public function submission(Request $request, int $task): JsonResponse
    {
        [$record] = $this->visibleTask($request, $task);
        $current = $record->current_submission_id ? DB::table('task_submissions')->find($record->current_submission_id) : null;

        return response()->json(['data' => $current ? $this->submissionData($current) : null]);
    }

    #[OpenApiResponse(200, description: 'Submission history ordered by descending submission_version.', type: 'array{data:list<'.self::SUBMISSION_TYPE.'>,meta:array{current_page:int,per_page:int,total:int,last_page:int}}')]
    #[OpenApiResponse(401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(403, description: 'The actor account is inactive.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(404, description: 'Task does not exist or is outside the actor scope.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(422, description: 'Pagination query is invalid.', type: self::ERROR_RESPONSE_TYPE)]
    #[QueryParameter('page', 'One-based page number.', type: 'integer', default: 1)]
    #[QueryParameter('per_page', 'Page size: 1–50.', type: 'integer', default: 20)]
    public function versions(Request $request, int $task): JsonResponse
    {
        $page = $this->pagination($request);
        $this->visibleTask($request, $task);
        $versions = DB::table('task_submissions')->where('task_id', $task)
            ->orderByDesc('submission_version')
            ->paginate($page['per_page'], ['*'], 'page', $page['page']);

        return response()->json($this->paginated($versions, fn ($row) => $this->submissionData($row)));
    }

    #[OpenApiResponse(200, description: 'Replay of the previously stored Submission for the same actor, key, and payload.', type: 'array{data:'.self::SUBMISSION_TYPE.'}')]
    #[OpenApiResponse(201, description: 'New Submission version stored.', type: 'array{data:'.self::SUBMISSION_TYPE.'}')]
    #[OpenApiResponse(401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(403, description: 'Only the assigned active Customer may submit.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(404, description: 'Task does not exist.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(409, description: 'Submission version is stale, Task state conflicts, or the key was used with another payload.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(422, description: 'Payload, UUID Idempotency-Key, or attachments are invalid.', type: self::ERROR_RESPONSE_TYPE)]
    #[HeaderParameter('Idempotency-Key', 'UUID used to replay the same Submission request.', required: true, type: 'string', format: 'uuid')]
    public function submit(Request $request, int $task): JsonResponse
    {
        $data = $request->validate([
            'expected_submission_version' => 'required|integer|min:0|max:'.TaskSubmissionRequestHash::MAX_SAFE_INTEGER,
            'content' => 'nullable|string|max:10000',
            'file_ids' => 'sometimes|array|max:5',
            'file_ids.*' => 'required|integer|distinct|min:1|max:'.TaskSubmissionRequestHash::MAX_SAFE_INTEGER,
        ]);
        $key = $request->header('Idempotency-Key');
        if (! is_string($key) || ! Str::isUuid($key)) {
            return $this->error($request, 422, 'invalid_idempotency_key');
        }
        $content = isset($data['content']) ? preg_replace('/\r\n?/', "\n", $data['content']) : null;
        $content = trim($content ?? '') === '' ? null : $content;
        $fileIds = array_map('intval', $data['file_ids'] ?? []);
        sort($fileIds, SORT_NUMERIC);
        if ($content === null && $fileIds === []) {
            return $this->error($request, 422, 'empty_submission');
        }
        $hash = TaskSubmissionRequestHash::make((int) $data['expected_submission_version'], $content, $fileIds);

        return DB::transaction(function () use ($request, $task, $data, $key, $hash, $content, $fileIds) {
            [$record, $program] = $this->lockedTask($request, $task, 'assignee');
            $replay = DB::table('task_submissions')->where('task_id', $task)
                ->where('customer_id', $request->user()->id)->where('idempotency_key', $key)->first();
            if ($replay) {
                return $replay->request_hash === $hash
                    ? response()->json(['data' => $this->submissionData($replay)])
                    : $this->error($request, 409, 'idempotency_conflict');
            }
            if (! in_array($program->status, ['active', 'paused'], true)
                || ! in_array($record->status, ['todo', 'in_progress', 'submitted'], true)) {
                return $this->error($request, 409, 'task_state_conflict');
            }
            $current = $record->current_submission_id ? DB::table('task_submissions')->find($record->current_submission_id) : null;
            $version = $current ? (int) $current->submission_version : 0;
            if ($version !== (int) $data['expected_submission_version']
                || ($current && ! in_array($current->status, ['submitted', 'revision_requested'], true))
                || ($record->status === 'submitted' && (! $current || $current->status !== 'submitted'))) {
                return $this->error($request, 409, 'stale_submission_version');
            }
            $files = $this->files($request, $fileIds);
            $now = now('UTC');
            $id = DB::table('task_submissions')->insertGetId([
                'task_id' => $task,
                'customer_id' => $request->user()->id,
                'content' => $content,
                'status' => 'submitted',
                'submitted_at' => $now,
                'submission_version' => $version + 1,
                'idempotency_key' => $key,
                'request_hash' => $hash,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->linkFiles($files, 'task_submission', $id);
            DB::table('tasks')->where('id', $task)->update([
                'current_submission_id' => $id,
                'status' => 'submitted',
                'version' => $record->version + 1,
                'updated_at' => $now,
            ]);

            return response()->json(['data' => $this->submissionData(DB::table('task_submissions')->find($id))], 201);
        }, 3);
    }

    #[HeaderParameter('Idempotency-Key', 'UUID retained for 30 days to replay this feedback decision.', required: true, type: 'string', format: 'uuid')]
    #[OpenApiResponse(200, description: 'Feedback decision for the exact current Submission version.', type: 'array{data:'.self::FEEDBACK_TYPE.'}')]
    #[OpenApiResponse(401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(403, description: 'Only the Program owner may provide feedback.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(404, description: 'Task does not exist.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(409, description: 'Submission version is stale, Task state conflicts, or the key was used with another payload.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(422, description: 'Feedback, expected version, or UUID Idempotency-Key is invalid.', type: self::ERROR_RESPONSE_TYPE)]
    public function review(Request $request, int $task): JsonResponse
    {
        return $this->feedback($request, $task, 'accepted');
    }

    #[OpenApiResponse(200, description: 'Feedback decision for the exact current Submission version.', type: 'array{data:'.self::FEEDBACK_TYPE.'}')]
    #[OpenApiResponse(401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(403, description: 'Only the Program owner may provide feedback.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(404, description: 'Task does not exist.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(409, description: 'Submission version is stale, Task state conflicts, or the key was used with another payload.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(422, description: 'Feedback, expected version, or UUID Idempotency-Key is invalid.', type: self::ERROR_RESPONSE_TYPE)]
    #[HeaderParameter('Idempotency-Key', 'UUID retained for 30 days to replay this feedback decision.', required: true, type: 'string', format: 'uuid')]
    public function requestRevision(Request $request, int $task): JsonResponse
    {
        return $this->feedback($request, $task, 'revision_requested');
    }

    private function feedback(Request $request, int $task, string $decision): JsonResponse
    {
        if (array_diff(array_keys($request->all()), ['expected_submission_version', 'feedback']) !== []) {
            return $this->error($request, 422, 'invalid_feedback_payload');
        }
        $data = $request->validate([
            'expected_submission_version' => 'required|integer|min:1',
            'feedback' => ($decision === 'revision_requested' ? 'required' : 'nullable').'|string|max:5000',
        ]);
        $key = $request->header('Idempotency-Key');
        if (! is_string($key) || ! Str::isUuid($key)) {
            return $this->error($request, 422, 'invalid_idempotency_key');
        }
        $key = strtolower($key);
        $feedback = isset($data['feedback']) ? preg_replace('/\r\n?/', "\n", $data['feedback']) : null;
        $feedback = trim($feedback ?? '') === '' ? null : $feedback;
        if ($decision === 'revision_requested' && $feedback === null) {
            return $this->error($request, 422, 'feedback_required');
        }

        $path = "/api/v1/tasks/{$task}/submission/".($decision === 'accepted' ? 'review' : 'request-revision');
        $payload = json_encode([
            'expected_submission_version' => (int) $data['expected_submission_version'],
            'feedback' => $feedback,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR);
        $hash = hash('sha256', "POST\n{$path}\n{$payload}");

        return DB::transaction(function () use ($request, $task, $decision, $data, $key, $path, $hash, $feedback) {
            $actor = DB::table('users')->where('id', $request->user()->id)->lockForUpdate()->first();
            abort_unless($actor && $actor->status === 'active', 403);
            [$record, $program] = $this->lockedTask($request, $task, 'owner');
            $now = CarbonImmutable::now('UTC');
            $receipt = DB::table('idempotency_receipts')->where('actor_id', $actor->id)
                ->where('idempotency_key', $key)->first();
            if ($receipt) {
                if ($receipt->expires_at !== null && CarbonImmutable::parse($receipt->expires_at)->lte($now)) {
                    DB::table('idempotency_receipts')->where('id', $receipt->id)->delete();
                } elseif ($receipt->method !== 'POST' || $receipt->canonical_path !== $path
                    || ! hash_equals($receipt->request_hash, $hash)) {
                    return $this->error($request, 409, 'idempotency_key_reused');
                } else {
                    return response()->json(json_decode($receipt->response_snapshot, true, 512, JSON_THROW_ON_ERROR), 200);
                }
            }
            if (! in_array($program->status, ['active', 'paused'], true)
                || $record->status !== 'submitted' || ! $record->current_submission_id) {
                return $this->error($request, 409, 'task_state_conflict');
            }
            $current = DB::table('task_submissions')->where('id', $record->current_submission_id)->lockForUpdate()->first();
            if (! $current || $current->status !== 'submitted'
                || (int) $current->submission_version !== (int) $data['expected_submission_version']) {
                return $this->error($request, 409, 'stale_submission_version');
            }
            DB::table('task_submissions')->where('id', $current->id)->update([
                'status' => $decision,
                'feedback' => $feedback,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('tasks')->where('id', $task)->update([
                'status' => $decision === 'accepted' ? 'completed' : 'in_progress',
                'version' => $record->version + 1,
                'updated_at' => $now,
            ]);
            $response = ['data' => $this->feedbackData(DB::table('task_submissions')->find($current->id), $record->version + 1)];
            DB::table('idempotency_receipts')->insert([
                'id' => (string) Str::uuid(),
                'actor_id' => $actor->id,
                'idempotency_key' => $key,
                'method' => 'POST',
                'canonical_path' => $path,
                'request_hash' => $hash,
                'response_status' => 200,
                'response_snapshot' => json_encode($response, JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'expires_at' => $now->addDays(30),
            ]);

            return response()->json($response, 200);
        }, 3);
    }

    private function mutateTask(Request $request, int $task, string $role, array $data, callable $change): JsonResponse
    {
        return DB::transaction(function () use ($request, $task, $role, $data, $change) {
            [$record, $program] = $this->lockedTask($request, $task, $role);
            if ((int) $record->version !== (int) $data['expected_version']) {
                return $this->error($request, 409, 'stale_task_version');
            }

            return $change($record, $program);
        }, 3);
    }

    private function pagination(Request $request): array
    {
        $data = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]) + ['page' => 1, 'per_page' => 20];

        return ['page' => (int) $data['page'], 'per_page' => (int) $data['per_page']];
    }

    private function paginated(LengthAwarePaginator $page, callable $map): array
    {
        return [
            'data' => $page->getCollection()->map($map)->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ];
    }

    private function lockedTask(Request $request, int $task, string $requiredRole): array
    {
        $context = DB::table('tasks')->where('id', $task)->first();
        abort_unless($context, 404);
        $program = DB::table('programs')->where('id', $context->program_id)->lockForUpdate()->first();
        $record = DB::table('tasks')->where('id', $task)->lockForUpdate()->first();
        abort_unless($program && $record, 404);
        abort_unless($this->role($program, $request, $record) === $requiredRole, 403);

        return [$record, $program];
    }

    private function visibleTask(Request $request, int $task): array
    {
        $record = DB::table('tasks')->find($task);
        abort_unless($record, 404);
        $program = DB::table('programs')->find($record->program_id);
        abort_unless($program && $this->role($program, $request, $record), 404);

        return [$record, $program];
    }

    private function role(stdClass $program, Request $request, ?stdClass $task = null): ?string
    {
        $actor = $request->user();
        if ($actor->role === 'tutor' && DB::table('tutor_profiles')->where('id', $program->owner_tutor_profile_id)
            ->where('user_id', $actor->id)->exists()) {
            return 'owner';
        }
        if ($actor->role === 'customer' && $this->member($program->id, $actor->id)
            && ($task === null || (int) $task->assigned_to === (int) $actor->id)) {
            return 'assignee';
        }

        return null;
    }

    private function member(int $program, int $user): bool
    {
        return DB::table('program_members')->where('program_id', $program)->where('user_id', $user)
            ->where('member_role', 'customer')->where('status', 'active')->exists();
    }

    private function files(Request $request, array $ids): array
    {
        $files = [];
        sort($ids, SORT_NUMERIC);
        foreach ($ids as $id) {
            $file = File::query()->whereKey($id)->lockForUpdate()->first();
            abort_unless($file && $file->owner_id === $request->user()->id
                && $file->purpose === 'task_attachment' && $file->isComplete()
                && $file->size_bytes > 0 && $file->size_bytes <= 5_000_000
                && in_array($file->mime_type, [
                    'application/pdf', 'image/jpeg', 'image/png',
                    'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ], true), 422, 'Invalid task attachment.');
            $files[] = $file;
        }

        return $files;
    }

    private function linkFiles(array $files, string $type, int $id): void
    {
        foreach ($files as $file) {
            DB::table('file_links')->insert([
                'file_id' => $file->id, 'resource_type' => $type,
                'resource_id' => $id, 'purpose' => 'task_attachment',
                'created_at' => now('UTC'),
            ]);
        }
    }

    private function taskData(stdClass $record): array
    {
        return [
            'id' => $record->id, 'program_id' => $record->program_id,
            'title' => $record->title, 'description' => $record->description,
            'due_at' => $this->rfc3339Utc($record->due_at), 'assigned_to' => $record->assigned_to,
            'status' => $record->status, 'version' => $record->version,
            'current_submission_id' => $record->current_submission_id,
            'file_ids' => DB::table('file_links')->where('resource_type', 'task')->where('resource_id', $record->id)->pluck('file_id'),
        ];
    }

    private function submissionData(stdClass $record): array
    {
        return [
            'id' => $record->id, 'task_id' => $record->task_id,
            'submission_version' => $record->submission_version,
            'content' => $record->content, 'status' => $record->status,
            'submitted_at' => $this->rfc3339Utc($record->submitted_at), 'feedback' => $record->feedback,
            'reviewed_by' => $record->reviewed_by, 'reviewed_at' => $this->rfc3339Utc($record->reviewed_at),
            'file_ids' => DB::table('file_links')->where('resource_type', 'task_submission')->where('resource_id', $record->id)->pluck('file_id'),
        ];
    }

    private function feedbackData(stdClass $record, int $taskVersion): array
    {
        return [
            'id' => $record->id, 'task_id' => $record->task_id,
            'submission_version' => $record->submission_version,
            'version' => $taskVersion,
            'status' => $record->status, 'feedback' => $record->feedback,
            'reviewed_by' => $record->reviewed_by, 'reviewed_at' => $this->rfc3339Utc($record->reviewed_at),
        ];
    }

    private function rfc3339Utc(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $timestamp = CarbonImmutable::parse($value)->utc();
        $formatted = $timestamp->format('Y-m-d\TH:i:s');
        $microseconds = $timestamp->format('u');

        if ($microseconds !== '000000') {
            $formatted .= '.'.rtrim($microseconds, '0');
        }

        return $formatted.'Z';
    }

    private function error(Request $request, int $status, string $code): JsonResponse
    {
        return ApiErrorResponse::make($status, $code, str_replace('_', ' ', $code),
            (string) $request->attributes->get('request_id'));
    }
}
