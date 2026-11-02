<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\OverviewRequest;
use App\Http\Resources\AnnouncementResource;
use App\Http\Resources\ProgressRecordResource;
use App\Http\Resources\SessionResource;
use App\Models\Announcement;
use App\Models\Program;
use App\Models\ProgramSession;
use App\Models\ProgressRecord;
use App\Models\User;
use App\Support\BeD04Workspace;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Attributes\Header as OpenApiHeader;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProgramOverviewController extends Controller
{
    private const ERROR_RESPONSE_TYPE = 'array{code: string, message: string, request_id: string, errors: array<string, mixed>}';

    /** Build the Workspace summary from one repeatable, read-only database snapshot. */
    #[OpenApiResponse(
        status: 200,
        description: 'Program Workspace overview.',
        type: 'array{data: array{next_session: SessionResource|null, task_counts: array{todo: int, in_progress: int, submitted: int, completed: int, cancelled: int}, latest_progress: ProgressRecordResource|null, latest_announcement: AnnouncementResource|null, capabilities: array{can_create_session: bool, can_create_task: bool, can_record_progress: bool, can_create_fee_record: bool, can_create_announcement: bool, can_create_review: bool}}}',
    )]
    #[OpenApiResponse(status: 401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 403, description: 'The account is inactive.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 404, description: 'Program not found in the actor scope.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 422, description: 'The request must not include query parameters.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 200)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 401)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 403)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 404)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 422)]
    /** Get the Program workspace overview. */
    public function show(OverviewRequest $request, int $program): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $requestId = (string) $request->attributes->get('request_id');

        $data = DB::transaction(function () use ($request, $actor, $program, $requestId): array {
            DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');

            $record = app(BeD04Workspace::class)->visible($actor, $program, $requestId);
            $now = CarbonImmutable::now('UTC');

            $nextSession = ProgramSession::query()
                ->with('actual')
                ->where('program_id', $record->id)
                ->where('status', 'scheduled')
                ->where('planned_starts_at', '>', $now->format('Y-m-d H:i:s.uP'))
                ->orderBy('planned_starts_at')
                ->orderBy('id')
                ->first();

            $taskCounts = array_fill_keys([
                'todo',
                'in_progress',
                'submitted',
                'completed',
                'cancelled',
            ], 0);
            $groupedTaskCounts = DB::table('tasks')
                ->where('program_id', $record->id)
                ->select('status')
                ->selectRaw('COUNT(*) AS total')
                ->groupBy('status')
                ->pluck('total', 'status');

            foreach ($taskCounts as $status => $count) {
                $taskCounts[$status] = (int) ($groupedTaskCounts[$status] ?? $count);
            }

            $latestProgress = ProgressRecord::query()
                ->where('program_id', $record->id)
                ->orderByRaw('recorded_at DESC NULLS LAST')
                ->orderByDesc('id')
                ->first();

            $latestAnnouncement = Announcement::query()
                ->with('files')
                ->where('program_id', $record->id)
                ->whereNotNull('published_at')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->first();

            return [
                'next_session' => $nextSession === null
                    ? null
                    : SessionResource::make($nextSession)->resolve($request),
                'task_counts' => $taskCounts,
                'latest_progress' => $latestProgress === null
                    ? null
                    : ProgressRecordResource::make($latestProgress)->resolve($request),
                'latest_announcement' => $latestAnnouncement === null
                    ? null
                    : AnnouncementResource::make($latestAnnouncement)->resolve($request),
                'capabilities' => $this->capabilities($actor, $record),
            ];
        }, 3);

        return response()->json(['data' => $data]);
    }

    /** @return array<string, bool> */
    private function capabilities(User $actor, Program $program): array
    {
        $isOwner = $actor->role === 'tutor'
            && $program->ownerTutorProfile()
                ->where('user_id', $actor->id)
                ->exists();
        $active = $program->status === 'active';
        $progressWritable = in_array($program->status, ['active', 'paused'], true);
        $canCreateReview = $actor->role === 'customer'
            && $program->status === 'completed'
            && ! DB::table('reviews')
                ->where('program_id', $program->id)
                ->where('customer_id', $actor->id)
                ->exists();

        return [
            'can_create_session' => $isOwner && $active,
            'can_create_task' => $isOwner && $active,
            'can_record_progress' => $isOwner && $progressWritable,
            'can_create_fee_record' => $isOwner && $active,
            'can_create_announcement' => $isOwner && $active,
            'can_create_review' => $canCreateReview,
        ];
    }
}
