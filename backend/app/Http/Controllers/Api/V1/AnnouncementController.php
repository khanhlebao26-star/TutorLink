<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnnouncementIndexRequest;
use App\Http\Requests\AnnouncementShowRequest;
use App\Http\Requests\PublishAnnouncementRequest;
use App\Http\Requests\StoreAnnouncementRequest;
use App\Http\Requests\UpdateAnnouncementRequest;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use App\Models\File;
use App\Models\Program;
use App\Models\User;
use App\Support\ApiErrorResponse;
use App\Support\BeD04Workspace;
use App\Support\NotificationOutboxWriter;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Attributes\Header as OpenApiHeader;
use Dedoc\Scramble\Attributes\HeaderParameter as OpenApiHeaderParameter;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AnnouncementController extends Controller
{
    private const ERROR_RESPONSE_TYPE = 'array{code: string, message: string, request_id: string, errors: array<string, mixed>}';

    private const ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    #[OpenApiResponse(status: 200, description: 'Paginated Announcements.', type: 'array{data: list<AnnouncementResource>, meta: array{current_page: int, per_page: int, total: int, last_page: int}}')]
    #[OpenApiResponse(status: 401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 403, description: 'The account or requested Announcement state is forbidden.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 404, description: 'Program is not visible to the actor.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 422, description: 'Filter or pagination parameters are invalid.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 200)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 401)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 403)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 404)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 422)]
    /** List Announcements for a Program. */
    public function index(AnnouncementIndexRequest $request, int $program): JsonResponse
    {
        $state = $request->validated('state', 'published');
        $perPage = (int) $request->validated('per_page', 20);
        $page = (int) $request->validated('page', 1);
        $query = Announcement::query()->where('program_id', $program);

        if ($state === 'published') {
            $query->whereNotNull('published_at');
        } elseif ($state === 'draft') {
            $query->whereNull('published_at');
        }

        $total = (clone $query)->count();
        $query->with('files');

        match ($state) {
            'draft' => $query->orderByRaw('created_at DESC NULLS LAST, id DESC'),
            'all' => $query
                ->orderByRaw('CASE WHEN published_at IS NULL THEN 0 ELSE 1 END ASC')
                ->orderByRaw('CASE WHEN published_at IS NULL THEN created_at END DESC NULLS LAST')
                ->orderByRaw('CASE WHEN published_at IS NOT NULL THEN published_at END DESC NULLS LAST')
                ->orderByDesc('id'),
            default => $query->orderByRaw('published_at DESC, id DESC'),
        };

        $items = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        return response()->json([
            'data' => AnnouncementResource::collection($items)->resolve($request),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    #[OpenApiResponse(status: 200, description: 'Announcement resource.', type: 'array{data: AnnouncementResource}')]
    #[OpenApiResponse(status: 401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 403, description: 'The account is forbidden.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 404, description: 'Announcement is not visible to the actor.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 422, description: 'Request parameters are invalid.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 200)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 401)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 403)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 404)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 422)]
    /** Show an Announcement. */
    public function show(AnnouncementShowRequest $request, int $announcement): JsonResponse
    {
        $resource = Announcement::query()->with('files')->findOrFail($announcement);

        return response()->json(['data' => (new AnnouncementResource($resource))->resolve($request)]);
    }

    #[OpenApiResponse(status: 201, description: 'Announcement draft created.', type: 'array{data: AnnouncementResource}')]
    #[OpenApiResponse(status: 401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 403, description: 'Only the Program owner may create a draft.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 404, description: 'Program is not visible to the actor.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 409, description: 'Program state does not allow this mutation.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 422, description: 'Request fields or file selection are invalid.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 201)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 401)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 403)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 404)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 409)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 422)]
    /** Create an Announcement draft. */
    public function store(StoreAnnouncementRequest $request, int $program): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $requestId = (string) $request->attributes->get('request_id');
        $input = $request->validated();
        $fileIds = $input['file_ids'] ?? [];

        $announcement = app(BeD04Workspace::class)->withLockedOwnerProgram(
            $actor,
            $program,
            $requestId,
            function (Program $lockedProgram, User $lockedActor) use ($input, $fileIds, $requestId): Announcement {
                $this->assertWritableProgram($lockedProgram, $requestId);
                $timestamp = now('UTC')->format('Y-m-d H:i:s.uP');
                $announcementId = DB::table('announcements')->insertGetId([
                    'program_id' => $lockedProgram->id,
                    'author_id' => $lockedActor->id,
                    'title' => $input['title'],
                    'body' => $input['body'],
                    'published_at' => null,
                    'version' => 1,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
                $fileIds = $this->sortedIds($fileIds);
                $workspace = app(BeD04Workspace::class);
                $files = $workspace->lockFiles($fileIds);
                $links = $workspace->lockFileLinksForFiles($fileIds);
                $this->assertEligibleFiles($fileIds, $files, $links, $lockedActor, null, $requestId);

                $this->applyFileDiff($lockedProgram, $announcementId, [], $fileIds, [], $files, 1, $timestamp, $lockedActor, $requestId);

                return Announcement::query()->with('files')->findOrFail($announcementId);
            },
        );

        return response()->json(['data' => (new AnnouncementResource($announcement))->resolve($request)], 201);
    }

    #[OpenApiResponse(status: 200, description: 'Announcement draft updated.', type: 'array{data: AnnouncementResource}')]
    #[OpenApiResponse(status: 401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 403, description: 'Only the Program owner may update a draft.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 404, description: 'Announcement is not visible to the actor.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 409, description: 'Version, publication state, or Program state conflicts with the request.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 422, description: 'Request fields or file selection are invalid.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 200)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 401)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 403)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 404)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 409)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 422)]
    /** Update an Announcement draft. */
    public function update(UpdateAnnouncementRequest $request, int $announcement): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $requestId = (string) $request->attributes->get('request_id');
        $programId = (int) $request->attributes->get('d04_program_id');
        $input = $request->validated();

        $resource = app(BeD04Workspace::class)->withLockedOwnerProgram(
            $actor,
            $programId,
            $requestId,
            function (Program $lockedProgram, User $lockedActor) use ($input, $announcement, $requestId): Announcement {
                $this->assertWritableProgram($lockedProgram, $requestId);
                $resource = Announcement::query()->whereKey($announcement)->lockForUpdate()->first();

                if ($resource === null || (int) $resource->program_id !== (int) $lockedProgram->id) {
                    $this->fail(404, 'announcement_not_found', 'Announcement not found.', $requestId);
                }

                if ((int) $resource->version !== (int) $input['expected_version']) {
                    throw new HttpResponseException(ApiErrorResponse::staleVersion((int) $resource->version, $requestId));
                }

                if ($resource->published_at !== null) {
                    $this->fail(409, 'announcement_state_conflict', 'Published Announcements cannot be changed.', $requestId);
                }

                $title = $input['title'] ?? $resource->title;
                $body = $input['body'] ?? $resource->body;
                $filesWereRequested = array_key_exists('file_ids', $input);
                $currentLinks = $filesWereRequested
                    ? DB::table('file_links')
                        ->where('resource_type', 'announcement')
                        ->where('resource_id', $resource->id)
                        ->orderBy('file_id')
                        ->get()
                    : collect();
                $oldIds = $this->sortedIds($currentLinks->pluck('file_id')->all());
                $newIds = $filesWereRequested ? $this->sortedIds($input['file_ids']) : $oldIds;
                $changedIds = $filesWereRequested && $oldIds !== $newIds;
                $changedContent = $title !== $resource->title || $body !== $resource->body;
                $files = new EloquentCollection;
                $lockedLinks = new Collection;

                if ($filesWereRequested) {
                    $workspace = app(BeD04Workspace::class);
                    $allIds = $this->sortedIds(array_merge($oldIds, $newIds));
                    $files = $workspace->lockFiles($allIds);
                    $lockedLinks = $workspace->lockFileLinksForFiles($allIds);
                    $this->assertEligibleFiles($newIds, $files, $lockedLinks, $lockedActor, (int) $resource->id, $requestId);
                    $this->assertCurrentLinks($oldIds, $lockedLinks, (int) $resource->id, $requestId);
                }

                if (! $changedContent && ! $changedIds) {
                    return $resource->load('files');
                }

                $newVersion = (int) $resource->version + 1;
                $timestamp = now('UTC')->format('Y-m-d H:i:s.uP');
                DB::table('announcements')->where('id', $resource->id)->update([
                    'title' => $title,
                    'body' => $body,
                    'version' => $newVersion,
                    'updated_at' => $timestamp,
                ]);

                if ($filesWereRequested && $changedIds) {
                    $this->applyFileDiff(
                        $lockedProgram,
                        (int) $resource->id,
                        $oldIds,
                        $newIds,
                        $lockedLinks->all(),
                        $files,
                        $newVersion,
                        $timestamp,
                        $lockedActor,
                        $requestId,
                    );
                }

                return Announcement::query()->with('files')->findOrFail($resource->id);
            },
        );

        return response()->json(['data' => (new AnnouncementResource($resource))->resolve($request)]);
    }

    #[OpenApiResponse(status: 200, description: 'Announcement published.', type: 'array{data: AnnouncementResource}')]
    #[OpenApiResponse(status: 401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 403, description: 'Only the Program owner may publish.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 404, description: 'Announcement is not visible to the actor.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 409, description: 'Version, publication state, or Program state conflicts with the request.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 422, description: 'Expected version or idempotency key is invalid.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiHeaderParameter('Idempotency-Key', description: 'Required UUID key for replay-safe publishing.', type: 'string', format: 'uuid', required: true)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 200)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 401)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 403)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 404)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 409)]
    #[OpenApiHeader('X-Request-ID', description: 'Request correlation identifier.', type: 'string', format: 'uuid', required: true, status: 422)]
    /** Publish an Announcement. */
    public function publish(PublishAnnouncementRequest $request, int $announcement): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $requestId = (string) $request->attributes->get('request_id');
        $programId = (int) $request->attributes->get('d04_program_id');
        $input = $request->validated();
        $method = 'POST';
        $path = "/api/v1/announcements/{$announcement}/publish";
        $idempotencyKey = (string) $request->attributes->get('d04_idempotency_key');
        $canonicalJson = json_encode(
            ['expected_version' => (int) $input['expected_version']],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR,
        );
        $requestHash = hash('sha256', "{$method}\n{$path}\n{$canonicalJson}");

        $result = app(BeD04Workspace::class)->withLockedOwnerProgram(
            $actor,
            $programId,
            $requestId,
            function (Program $lockedProgram, User $lockedActor) use ($announcement, $requestId, $input, $method, $path, $idempotencyKey, $requestHash, $request): array {
                $resource = Announcement::query()->whereKey($announcement)->lockForUpdate()->first();

                if ($resource === null || (int) $resource->program_id !== (int) $lockedProgram->id) {
                    $this->fail(404, 'announcement_not_found', 'Announcement not found.', $requestId);
                }

                $now = CarbonImmutable::now('UTC');
                $receipt = DB::table('idempotency_receipts')
                    ->where('actor_id', $lockedActor->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($receipt !== null) {
                    if ($receipt->expires_at !== null && CarbonImmutable::parse($receipt->expires_at)->lessThanOrEqualTo($now)) {
                        DB::table('idempotency_receipts')->where('id', $receipt->id)->delete();

                        return ['expired_receipt_removed' => true];
                    } else {
                        if (
                            $receipt->method !== $method
                            || $receipt->canonical_path !== $path
                            || ! hash_equals($receipt->request_hash, $requestHash)
                        ) {
                            $this->fail(409, 'idempotency_conflict', 'Idempotency-Key was used for another request.', $requestId);
                        }

                        $snapshot = json_decode($receipt->response_snapshot, true, 512, JSON_THROW_ON_ERROR);

                        if (! is_array($snapshot)) {
                            throw new \LogicException('Stored Announcement publish response is invalid.');
                        }

                        return ['status' => (int) $receipt->response_status, 'body' => $snapshot];
                    }
                }

                $this->assertWritableProgram($lockedProgram, $requestId);

                if ((int) $resource->version !== (int) $input['expected_version']) {
                    throw new HttpResponseException(ApiErrorResponse::staleVersion((int) $resource->version, $requestId));
                }

                if ($resource->published_at !== null) {
                    $this->fail(409, 'announcement_state_conflict', 'Published Announcements cannot be published again.', $requestId);
                }

                $workspace = app(BeD04Workspace::class);
                $currentLinks = DB::table('file_links')
                    ->where('resource_type', 'announcement')
                    ->where('resource_id', $resource->id)
                    ->orderBy('file_id')
                    ->orderBy('id')
                    ->get();
                $fileIds = $this->sortedIds($currentLinks->pluck('file_id')->all());
                $files = $workspace->lockFiles($fileIds);
                $lockedLinks = $workspace->lockFileLinksForFiles($fileIds);
                $this->assertEligibleFiles($fileIds, $files, $lockedLinks, $lockedActor, (int) $resource->id, $requestId);
                $this->assertCurrentLinks($fileIds, $lockedLinks, (int) $resource->id, $requestId);

                $publishedAt = $now->format('Y-m-d H:i:s.uP');
                $newVersion = (int) $resource->version + 1;
                DB::table('announcements')->where('id', $resource->id)->update([
                    'published_at' => $publishedAt,
                    'version' => $newVersion,
                    'updated_at' => $publishedAt,
                ]);

                $before = [
                    'program_id' => (int) $resource->program_id,
                    'state' => 'draft',
                    'version' => (int) $resource->version,
                    'published_at' => null,
                    'title_sha256' => hash('sha256', $resource->title),
                    'body_sha256' => hash('sha256', $resource->body),
                    'file_ids' => $fileIds,
                ];
                $after = [
                    ...$before,
                    'state' => 'published',
                    'version' => $newVersion,
                    'published_at' => $now->toIso8601ZuluString('microseconds'),
                ];
                DB::table('audit_logs')->insert([
                    'actor_id' => $lockedActor->id,
                    'action' => 'announcement.publish',
                    'resource_type' => 'announcement',
                    'resource_id' => $resource->id,
                    'before' => json_encode($before, JSON_THROW_ON_ERROR),
                    'after' => json_encode($after, JSON_THROW_ON_ERROR),
                    'reason' => null,
                    'request_id' => $requestId,
                    'created_at' => $publishedAt,
                ]);

                $recipientIds = DB::table('program_members')
                    ->join('users', 'users.id', '=', 'program_members.user_id')
                    ->where('program_members.program_id', $lockedProgram->id)
                    ->where('program_members.member_role', 'customer')
                    ->where('program_members.status', 'active')
                    ->where('users.status', 'active')
                    ->whereNull('users.deleted_at')
                    ->where('users.id', '<>', $lockedActor->id)
                    ->orderBy('users.id')
                    ->pluck('users.id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();

                app(NotificationOutboxWriter::class)->record(
                    'announcement_published',
                    'announcement',
                    (int) $resource->id,
                    "announcement:{$resource->id}:published",
                    $recipientIds,
                    ['deep_link' => "/programs/{$lockedProgram->id}?tab=announcements&announcement_id={$resource->id}"],
                );

                $resource = Announcement::query()->with('files')->findOrFail($resource->id);
                $body = ['data' => (new AnnouncementResource($resource))->resolve($request)];
                $receiptAt = $now->format('Y-m-d H:i:s.uP');
                DB::table('idempotency_receipts')->insert([
                    'id' => (string) Str::uuid(),
                    'actor_id' => $lockedActor->id,
                    'idempotency_key' => $idempotencyKey,
                    'method' => $method,
                    'canonical_path' => $path,
                    'request_hash' => $requestHash,
                    'response_status' => 200,
                    'response_snapshot' => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    'created_at' => $receiptAt,
                    'expires_at' => $now->addDays(30)->format('Y-m-d H:i:s.uP'),
                ]);

                return ['status' => 200, 'body' => $body];
            },
        );

        if (($result['expired_receipt_removed'] ?? false) === true) {
            return $this->publish($request, $announcement);
        }

        return response()->json($result['body'], $result['status']);
    }

    private function assertWritableProgram(Program $program, string $requestId): void
    {
        if (! in_array($program->status, ['active', 'paused'], true)) {
            $this->fail(409, 'program_state_conflict', 'Announcements cannot be changed for this Program state.', $requestId);
        }
    }

    /** @param list<int> $ids
     * @param  EloquentCollection<int, File>  $files
     * @param  Collection<int, object>  $links
     */
    private function assertEligibleFiles(array $ids, EloquentCollection $files, Collection $links, User $actor, ?int $announcementId, string $requestId): void
    {
        foreach ($ids as $id) {
            $file = $files->firstWhere('id', $id);

            if (
                $file === null
                || (int) $file->owner_id !== (int) $actor->id
                || $file->visibility !== 'private'
                || $file->purpose !== 'announcement_attachment'
                || ! $file->isComplete()
                || (int) $file->size_bytes < 1
                || (int) $file->size_bytes > 5_000_000
                || ! in_array($file->mime_type, self::ALLOWED_MIMES, true)
            ) {
                $this->invalidFiles($requestId);
            }

            foreach ($links->where('file_id', $id) as $link) {
                $belongsToCurrent = $announcementId !== null
                    && $link->resource_type === 'announcement'
                    && (int) $link->resource_id === $announcementId
                    && $link->purpose === 'announcement_attachment';

                if (! $belongsToCurrent) {
                    $this->invalidFiles($requestId);
                }
            }
        }
    }

    /** @param list<int> $oldIds
     * @param  Collection<int, object>  $links
     */
    private function assertCurrentLinks(array $oldIds, Collection $links, int $announcementId, string $requestId): void
    {
        $currentIds = $this->sortedIds($links
            ->filter(fn ($link): bool => $link->resource_type === 'announcement'
                && (int) $link->resource_id === $announcementId
                && $link->purpose === 'announcement_attachment'
            )
            ->pluck('file_id')
            ->all());

        if ($currentIds !== $oldIds) {
            $this->invalidFiles($requestId);
        }
    }

    /** @param list<int> $oldIds
     * @param  list<int>  $newIds
     * @param  list<object>  $allLinks
     * @param  EloquentCollection<int, File>  $files
     */
    private function applyFileDiff(
        Program $program,
        int $announcementId,
        array $oldIds,
        array $newIds,
        array $allLinks,
        EloquentCollection $files,
        int $version,
        string $timestamp,
        User $actor,
        string $requestId,
    ): void {
        foreach (array_diff($newIds, $oldIds) as $fileId) {
            DB::table('file_links')->insert([
                'file_id' => $fileId,
                'resource_type' => 'announcement',
                'resource_id' => $announcementId,
                'purpose' => 'announcement_attachment',
                'created_at' => $timestamp,
            ]);
            $this->auditFileDiff($actor, 'file.link', $program, $announcementId, $fileId, $version, $timestamp, $requestId);
        }

        foreach (array_diff($oldIds, $newIds) as $fileId) {
            $link = collect($allLinks)->first(fn ($row): bool => (int) $row->file_id === $fileId
                && $row->resource_type === 'announcement'
                && (int) $row->resource_id === $announcementId
            );

            if ($link === null) {
                $this->invalidFiles($requestId);
            }

            DB::table('file_links')->where('id', $link->id)->delete();
            $this->auditFileDiff($actor, 'file.unlink', $program, $announcementId, $fileId, $version, $timestamp, $requestId);
        }
    }

    private function auditFileDiff(
        User $actor,
        string $action,
        Program $program,
        int $announcementId,
        int $fileId,
        int $version,
        string $timestamp,
        string $requestId,
    ): void {
        $snapshot = [
            'program_id' => (int) $program->id,
            'file_id' => $fileId,
            'purpose' => 'announcement_attachment',
            'announcement_version' => $version,
        ];
        $before = $action === 'file.unlink' ? $snapshot : null;
        $after = $action === 'file.link' ? $snapshot : null;

        DB::table('audit_logs')->insert([
            'actor_id' => $actor->id,
            'action' => $action,
            'resource_type' => 'announcement',
            'resource_id' => $announcementId,
            'before' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'after' => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR),
            'reason' => null,
            'request_id' => $requestId,
            'created_at' => $timestamp,
        ]);
    }

    /** @param array<int, mixed> $ids
     * @return list<int>
     */
    private function sortedIds(array $ids): array
    {
        $ids = array_map('intval', $ids);
        sort($ids, SORT_NUMERIC);

        return $ids;
    }

    private function invalidFiles(string $requestId): never
    {
        $this->fail(422, 'validation_failed', 'The given data was invalid.', $requestId, [
            'file_ids' => ['One or more selected files are not eligible.'],
        ]);
    }

    /** @param array<string, mixed> $errors */
    private function fail(int $status, string $code, string $message, string $requestId, array $errors = []): never
    {
        throw new HttpResponseException(ApiErrorResponse::make($status, $code, $message, $requestId, $errors));
    }
}
