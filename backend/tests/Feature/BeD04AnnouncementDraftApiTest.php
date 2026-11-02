<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BeD04AnnouncementDraftApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_draft_create_list_detail_and_customer_scope(): void
    {
        $program = $this->program('D04 Fixture Active');
        $owner = $this->user('be-d04-owner@tutorlink.test');
        $path = $this->programPath($program);
        $auditCount = DB::table('audit_logs')->where('resource_type', 'announcement')->count();
        $outboxCount = DB::table('notification_outbox')->count();
        $created = $this->actingAs($owner)->postJson($path, [
            'title' => 'Lịch tuần này',
            'body' => 'Buổi học bắt đầu lúc 19:00.',
        ]);
        $this->recordApi('D04-08-POST-draft', 'owner', 'POST', $path, [
            'title' => 'Lịch tuần này', 'body' => 'Buổi học bắt đầu lúc 19:00.',
        ], $created);
        $created->assertCreated()
            ->assertJsonPath('data.program_id', $program->id)
            ->assertJsonPath('data.author_id', $owner->id)
            ->assertJsonPath('data.state', 'draft')
            ->assertJsonPath('data.files', [])
            ->assertJsonPath('data.published_at', null)
            ->assertJsonPath('data.version', 1);
        $this->assertSame([
            'id', 'program_id', 'author_id', 'state', 'title', 'body', 'files', 'published_at', 'version', 'created_at', 'updated_at',
        ], array_keys($created->json('data')));
        $this->assertSame($auditCount, DB::table('audit_logs')->where('resource_type', 'announcement')->count());
        $this->assertSame($outboxCount, DB::table('notification_outbox')->count());
        $announcementId = (int) $created->json('data.id');

        $member = $this->user('be-d04-late-member@tutorlink.test');
        $published = $this->actingAs($member)->getJson($path);
        $this->recordApi('D04-08-GET-published-default-member', 'member', 'GET', $path, null, $published);
        $published->assertOk()->assertJsonPath('meta.total', 2);
        $this->assertNotContains($announcementId, array_column($published->json('data'), 'id'));
        $this->assertNotContains('D04 Fixture Draft Owner Only', array_column($published->json('data'), 'title'));

        foreach (['draft', 'all'] as $state) {
            $forbidden = $this->getJson($path.'?state='.$state);
            $this->recordApi('D04-08-GET-customer-'.$state.'-forbidden', 'member', 'GET', $path.'?state='.$state, null, $forbidden);
            $forbidden->assertForbidden()->assertJsonPath('code', 'announcement_draft_forbidden');
            $this->assertArrayNotHasKey('data', $forbidden->json());
        }

        $drafts = $this->actingAs($owner)->getJson($path.'?state=draft');
        $this->recordApi('D04-08-GET-owner-drafts', 'owner', 'GET', $path.'?state=draft', null, $drafts);
        $drafts->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.id', $announcementId);

        $all = $this->getJson($path.'?state=all');
        $this->recordApi('D04-08-GET-owner-all', 'owner', 'GET', $path.'?state=all', null, $all);
        $all->assertOk()->assertJsonPath('meta.total', 4);
        $this->assertSame('draft', $all->json('data.0.state'));
        $this->assertSame('draft', $all->json('data.1.state'));
        $publishedIds = array_column(array_values(array_filter($all->json('data'), fn (array $item): bool => $item['state'] === 'published')), 'id');
        $this->assertSame([max($publishedIds), min($publishedIds)], $publishedIds);

        $detailPath = '/api/v1/announcements/'.$announcementId;
        $detail = $this->getJson($detailPath);
        $this->recordApi('D04-08-GET-draft-owner-detail', 'owner', 'GET', $detailPath, null, $detail);
        $detail->assertOk()->assertJsonPath('data.id', $announcementId);

        $hiddenDetail = $this->actingAs($member)->getJson($detailPath);
        $this->recordApi('D04-08-GET-draft-member-hidden', 'member', 'GET', $detailPath, null, $hiddenDetail);
        $hiddenDetail->assertNotFound()->assertJsonPath('code', 'announcement_not_found');

        $pagePastEnd = $this->actingAs($owner)->getJson($path.'?state=draft&page=9&per_page=1');
        $pagePastEnd->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 2)->assertJsonPath('meta.last_page', 2);
        $unknownFilter = $this->getJson($path.'?state=published&sort=title');
        $unknownFilter->assertUnprocessable()->assertJsonValidationErrors('sort');
    }

    public function test_draft_file_replace_and_detach_are_atomic_diff_and_noop_is_inert(): void
    {
        $program = $this->program('D04 Fixture Active');
        $owner = $this->user('be-d04-owner@tutorlink.test');
        $first = $this->completeAnnouncementFile($owner, 'first');
        $second = $this->completeAnnouncementFile($owner, 'second');
        $third = $this->completeAnnouncementFile($owner, 'third');
        $path = $this->programPath($program);
        $outboxCount = DB::table('notification_outbox')->count();

        $created = $this->actingAs($owner)->postJson($path, [
            'title' => 'Tài liệu buổi học',
            'body' => 'Ghi chú cho học viên.',
            'file_ids' => [$second, $first],
        ]);
        $this->recordApi('D04-08-POST-draft-files-sorted', 'owner', 'POST', $path, [
            'title' => 'Tài liệu buổi học', 'body' => 'Ghi chú cho học viên.', 'file_ids' => [$second, $first],
        ], $created);
        $created->assertCreated()->assertJsonPath('data.files.0.id', min($first, $second))->assertJsonPath('data.files.1.id', max($first, $second));
        $this->assertSame(['id', 'original_name', 'mime_type', 'size_bytes', 'complete'], array_keys($created->json('data.files.0')));
        $announcementId = (int) $created->json('data.id');
        $this->assertSame(2, $this->fileLinkAuditCount($announcementId));
        $this->assertSame($outboxCount, DB::table('notification_outbox')->count());

        $itemPath = '/api/v1/announcements/'.$announcementId;
        $bodyOnly = $this->patchJson($itemPath, ['expected_version' => 1, 'body' => 'Ghi chú đã cập nhật.']);
        $this->recordApi('D04-08-PATCH-omit-file-ids-preserve', 'owner', 'PATCH', $itemPath, [
            'expected_version' => 1, 'body' => 'Ghi chú đã cập nhật.',
        ], $bodyOnly);
        $bodyOnly->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.files.0.id', min($first, $second));
        $updatedAt = $bodyOnly->json('data.updated_at');
        $this->assertSame(2, DB::table('file_links')->where('resource_type', 'announcement')->where('resource_id', $announcementId)->count());
        $this->assertSame(2, $this->fileLinkAuditCount($announcementId), 'Draft text edits do not create content audit.');
        $this->assertSame($outboxCount, DB::table('notification_outbox')->count());

        $noop = $this->patchJson($itemPath, [
            'expected_version' => 2,
            'body' => 'Ghi chú đã cập nhật.',
            'file_ids' => [$first, $second],
        ]);
        $this->recordApi('D04-08-PATCH-noop', 'owner', 'PATCH', $itemPath, [
            'expected_version' => 2, 'body' => 'Ghi chú đã cập nhật.', 'file_ids' => [$first, $second],
        ], $noop);
        $noop->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.updated_at', $updatedAt);
        $this->assertSame(2, $this->fileLinkAuditCount($announcementId));
        $this->assertSame($outboxCount, DB::table('notification_outbox')->count());

        $replace = $this->patchJson($itemPath, ['expected_version' => 2, 'file_ids' => [$second, $third]]);
        $this->recordApi('D04-08-PATCH-file-diff', 'owner', 'PATCH', $itemPath, ['expected_version' => 2, 'file_ids' => [$second, $third]], $replace);
        $replace->assertOk()->assertJsonPath('data.version', 3)->assertJsonPath('data.files.0.id', min($second, $third))->assertJsonPath('data.files.1.id', max($second, $third));
        $this->assertEqualsCanonicalizing([$second, $third], DB::table('file_links')->where('resource_type', 'announcement')->where('resource_id', $announcementId)->pluck('file_id')->map(fn ($id): int => (int) $id)->all());
        $this->assertSame(4, $this->fileLinkAuditCount($announcementId), 'Replace audits only the added and removed file.');
        $this->assertSame($outboxCount, DB::table('notification_outbox')->count());

        $detach = $this->patchJson($itemPath, ['expected_version' => 3, 'file_ids' => []]);
        $this->recordApi('D04-08-PATCH-empty-unlinks', 'owner', 'PATCH', $itemPath, ['expected_version' => 3, 'file_ids' => []], $detach);
        $detach->assertOk()->assertJsonPath('data.version', 4)->assertJsonPath('data.files', []);
        $this->assertSame(0, DB::table('file_links')->where('resource_type', 'announcement')->where('resource_id', $announcementId)->count());
        $this->assertSame(3, DB::table('files')->whereIn('id', [$first, $second, $third])->count(), 'Unlink never deletes the File row or object.');
        $this->assertSame(6, $this->fileLinkAuditCount($announcementId));
        $this->assertSame($outboxCount, DB::table('notification_outbox')->count());

        $stale = $this->patchJson($itemPath, ['expected_version' => 3, 'file_ids' => []]);
        $this->recordApi('D04-08-PATCH-stale', 'owner', 'PATCH', $itemPath, ['expected_version' => 3, 'file_ids' => []], $stale);
        $stale->assertConflict()->assertJsonPath('code', 'stale_version')->assertJsonPath('errors.current_version', 4);
    }

    public function test_validation_file_eligibility_permissions_and_program_state(): void
    {
        $program = $this->program('D04 Fixture Active');
        $owner = $this->user('be-d04-owner@tutorlink.test');
        $path = $this->programPath($program);
        $valid = $this->completeAnnouncementFile($owner, 'validation');
        $announcementCount = DB::table('announcements')->count();
        $invalidBodies = [
            ['body' => 'Missing title'],
            ['title' => '   ', 'body' => 'Body'],
            ['title' => 'Title', 'body' => "\n\t"],
            ['title' => 'Title', 'body' => 'Body', 'file_ids' => null],
            ['title' => 'Title', 'body' => 'Body', 'file_ids' => [$valid, $valid]],
            ['title' => 'Title', 'body' => 'Body', 'file_ids' => [1, 2, 3, 4, 5, 6]],
            ['title' => 'Title', 'body' => 'Body', 'file_ids' => ['1']],
        ];

        foreach ($invalidBodies as $index => $body) {
            $response = $this->actingAs($owner)->postJson($path, $body);
            $this->recordApi('D04-08-POST-invalid-'.$index, 'owner', 'POST', $path, $body, $response);
            $response->assertUnprocessable()->assertJsonPath('code', 'validation_failed');
        }

        foreach (['wrong_owner', 'wrong_purpose', 'pending', 'rejected', 'valid'] as $fileKey) {
            $file = DB::table('files')->where('object_key', 'be-d04/fixtures/'.$fileKey.'.pdf')->first();
            $this->assertNotNull($file);
            $response = $this->postJson($path, [
                'title' => 'Allowed title',
                'body' => 'Allowed body',
                'file_ids' => [(int) $file->id],
            ]);
            $this->recordApi('D04-08-POST-file-ineligible-'.$fileKey, 'owner', 'POST', $path, ['title' => 'Allowed title', 'body' => 'Allowed body', 'file_ids' => [(int) $file->id]], $response);
            $response->assertUnprocessable()->assertJsonValidationErrors('file_ids');
        }
        $this->assertSame($announcementCount, DB::table('announcements')->count(), 'Rejected input and file choices leave no Draft row.');

        $member = $this->user('be-d04-late-member@tutorlink.test');
        $draft = Announcement::query()->where('program_id', $program->id)->whereNull('published_at')->firstOrFail();
        $memberPatch = $this->actingAs($member)->patchJson('/api/v1/announcements/'.$draft->id, ['expected_version' => 1, 'title' => 'Forbidden']);
        $this->recordApi('D04-08-PATCH-member-forbidden', 'member', 'PATCH', '/api/v1/announcements/'.$draft->id, ['expected_version' => 1, 'title' => 'Forbidden'], $memberPatch);
        $memberPatch->assertForbidden()->assertJsonPath('code', 'workspace_mutation_forbidden');

        $published = Announcement::query()->where('program_id', $program->id)->whereNotNull('published_at')->firstOrFail();
        $publishedPatch = $this->actingAs($owner)->patchJson('/api/v1/announcements/'.$published->id, ['expected_version' => $published->version, 'title' => $published->title]);
        $this->recordApi('D04-08-PATCH-published-conflict', 'owner', 'PATCH', '/api/v1/announcements/'.$published->id, ['expected_version' => $published->version, 'title' => $published->title], $publishedPatch);
        $publishedPatch->assertConflict()->assertJsonPath('code', 'announcement_state_conflict');

        $paused = $this->program('D04 Fixture Paused');
        $pausedWrite = $this->actingAs($owner)->postJson($this->programPath($paused), ['title' => 'Paused draft', 'body' => 'Paused remains writable.']);
        $this->recordApi('D04-08-POST-paused-allowed', 'owner', 'POST', $this->programPath($paused), ['title' => 'Paused draft', 'body' => 'Paused remains writable.'], $pausedWrite);
        $pausedWrite->assertCreated();

        $completed = $this->program('D04 Fixture Completed');
        $otherTutor = $this->user('be-d04-other-tutor@tutorlink.test');
        $terminal = $this->actingAs($otherTutor)->postJson($this->programPath($completed), ['title' => 'Terminal draft', 'body' => 'Not writable.']);
        $this->recordApi('D04-08-POST-terminal-conflict', 'owner', 'POST', $this->programPath($completed), ['title' => 'Terminal draft', 'body' => 'Not writable.'], $terminal);
        $terminal->assertConflict()->assertJsonPath('code', 'program_state_conflict');
    }

    private function completeAnnouncementFile(User $owner, string $suffix): int
    {
        $bytes = 'synthetic clean announcement file '.$suffix;
        $now = now('UTC')->format('Y-m-d H:i:s.uP');

        return DB::table('files')->insertGetId([
            'owner_id' => $owner->id,
            'disk' => 'local',
            'object_key' => 'tests/d04-announcement-'.$suffix.'-'.uniqid('', true).'.pdf',
            'original_name' => $suffix.'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($bytes),
            'visibility' => 'private',
            'checksum_sha256' => hash('sha256', $bytes),
            'scan_status' => 'clean',
            'purpose' => 'announcement_attachment',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function fileLinkAuditCount(int $announcementId): int
    {
        return DB::table('audit_logs')
            ->where('resource_type', 'announcement')
            ->where('resource_id', $announcementId)
            ->whereIn('action', ['file.link', 'file.unlink'])
            ->count();
    }

    private function program(string $title): Program
    {
        return Program::query()->where('title', $title)->firstOrFail();
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    private function programPath(Program $program): string
    {
        return '/api/v1/programs/'.$program->id.'/announcements';
    }

    /** @param array<string, mixed>|null $body */
    private function recordApi(string $case, string $actor, string $method, string $path, array|string|null $body, TestResponse $response): void
    {
        fwrite(STDOUT, json_encode([
            'case' => $case,
            'actor' => $actor,
            'request' => ['method' => $method, 'path' => $path, 'body' => $body],
            'response' => [
                'status' => $response->getStatusCode(),
                'headers' => ['X-Request-ID' => $response->headers->get('X-Request-ID')],
                'body' => json_decode($response->getContent(), false, 512, JSON_THROW_ON_ERROR),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR).PHP_EOL);
    }
}
