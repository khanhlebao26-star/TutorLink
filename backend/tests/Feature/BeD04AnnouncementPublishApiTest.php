<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BeD04AnnouncementPublishApiTest extends TestCase
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

    public function test_publish_commits_snapshot_audit_outbox_and_replay_receipt_once(): void
    {
        $program = $this->program('D04 Fixture Active');
        $owner = $this->user('be-d04-owner@tutorlink.test');
        $member = $this->user('be-d04-late-member@tutorlink.test');
        $fileId = $this->completeAnnouncementFile($owner);
        $path = '/api/v1/programs/'.$program->id.'/announcements';
        $created = $this->actingAs($owner)->postJson($path, [
            'title' => 'Kế hoạch học tập',
            'body' => 'Tài liệu và lịch học cho tuần này.',
            'file_ids' => [$fileId],
        ]);
        $created->assertCreated();
        $announcementId = (int) $created->json('data.id');
        $publishPath = '/api/v1/announcements/'.$announcementId.'/publish';
        $key = (string) Str::uuid();
        $auditBefore = DB::table('audit_logs')->where('resource_type', 'announcement')->where('resource_id', $announcementId)->count();
        $outboxBefore = DB::table('notification_outbox')->where('resource_type', 'announcement')->where('resource_id', $announcementId)->count();

        $published = $this->publish($publishPath, ['expected_version' => 1], $key, 'owner');
        $published->assertOk()
            ->assertJsonPath('data.id', $announcementId)
            ->assertJsonPath('data.state', 'published')
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.title', 'Kế hoạch học tập')
            ->assertJsonPath('data.files.0.id', $fileId);
        $this->assertNotNull($published->json('data.published_at'));

        $audit = DB::table('audit_logs')->where('resource_type', 'announcement')->where('resource_id', $announcementId)->where('action', 'announcement.publish')->first();
        $this->assertNotNull($audit);
        $this->assertSame($owner->id, (int) $audit->actor_id);
        $this->assertSame($published->headers->get('X-Request-ID'), $audit->request_id);
        $this->assertSame('draft', json_decode($audit->before, true)['state']);
        $after = json_decode($audit->after, true);
        $this->assertSame('published', $after['state']);
        $this->assertSame(2, $after['version']);
        $this->assertSame(hash('sha256', 'Kế hoạch học tập'), $after['title_sha256']);
        $this->assertSame(hash('sha256', 'Tài liệu và lịch học cho tuần này.'), $after['body_sha256']);
        $this->assertSame([$fileId], $after['file_ids']);
        $this->assertDatabaseHas('file_links', [
            'file_id' => $fileId,
            'resource_type' => 'announcement',
            'resource_id' => $announcementId,
            'purpose' => 'announcement_attachment',
        ]);
        $this->assertSame($auditBefore + 1, DB::table('audit_logs')->where('resource_type', 'announcement')->where('resource_id', $announcementId)->count());

        $event = DB::table('notification_outbox')->where('resource_type', 'announcement')->where('resource_id', $announcementId)->first();
        $this->assertNotNull($event);
        $this->assertSame($member->id, (int) $event->user_id);
        $this->assertSame('announcement_published', $event->type);
        $this->assertSame("announcement:{$announcementId}:published", $event->event_key);
        $this->assertSame(['deep_link' => "/programs/{$program->id}?tab=announcements&announcement_id={$announcementId}"], json_decode($event->payload, true));
        $this->assertSame($outboxBefore + 1, DB::table('notification_outbox')->where('resource_type', 'announcement')->where('resource_id', $announcementId)->count());

        $receipt = DB::table('idempotency_receipts')->where('actor_id', $owner->id)->where('idempotency_key', $key)->first();
        $this->assertNotNull($receipt);
        $this->assertSame($publishPath, $receipt->canonical_path);
        $this->assertSame('POST', $receipt->method);
        $this->assertSame(200, (int) $receipt->response_status);
        $this->assertEquals($published->json(), json_decode($receipt->response_snapshot, true));
        $this->assertTrue(Carbon::parse($receipt->expires_at)->equalTo(Carbon::parse($receipt->created_at)->addDays(30)));

        $replayed = $this->publish($publishPath, ['expected_version' => 1], strtoupper($key), 'owner-replay');
        $replayed->assertOk();
        $this->assertEquals($published->json(), $replayed->json());
        $this->assertSame($auditBefore + 1, DB::table('audit_logs')->where('resource_type', 'announcement')->where('resource_id', $announcementId)->count());
        $this->assertSame($outboxBefore + 1, DB::table('notification_outbox')->where('resource_type', 'announcement')->where('resource_id', $announcementId)->count());
        $this->assertSame(1, DB::table('idempotency_receipts')->where('actor_id', $owner->id)->where('idempotency_key', $key)->count());

        $changedKeyPayload = $this->publish($publishPath, ['expected_version' => 2], $key, 'owner-replay-mismatch');
        $changedKeyPayload->assertConflict()->assertJsonPath('code', 'idempotency_conflict');
        $newKey = $this->publish($publishPath, ['expected_version' => 2], (string) Str::uuid(), 'owner-new-key');
        $newKey->assertConflict()->assertJsonPath('code', 'announcement_state_conflict');

        $publishedEdit = $this->actingAs($owner)->patchJson('/api/v1/announcements/'.$announcementId, [
            'expected_version' => 2,
            'title' => 'Kế hoạch học tập',
        ]);
        $this->recordApi('D04-10-PATCH-published-immutable', 'owner', 'PATCH', '/api/v1/announcements/'.$announcementId, [
            'expected_version' => 2, 'title' => 'Kế hoạch học tập',
        ], $publishedEdit);
        $publishedEdit->assertConflict()->assertJsonPath('code', 'announcement_state_conflict');

        $publishedFileDelete = $this->actingAs($owner)->deleteJson('/api/v1/files/'.$fileId);
        $this->recordApi('D04-10-delete-published-file-immutable', 'owner', 'DELETE', '/api/v1/files/'.$fileId, null, $publishedFileDelete);
        $publishedFileDelete->assertConflict();

        $memberRead = $this->actingAs($member)->getJson('/api/v1/announcements/'.$announcementId);
        $memberRead->assertOk()->assertJsonPath('data.state', 'published');
    }

    public function test_publish_validates_key_and_version_and_uses_active_member_snapshot(): void
    {
        $program = $this->program('D04 Fixture Active');
        $owner = $this->user('be-d04-owner@tutorlink.test');
        $member = $this->user('be-d04-late-member@tutorlink.test');
        $path = '/api/v1/programs/'.$program->id.'/announcements';
        $firstDraft = $this->actingAs($owner)->postJson($path, ['title' => 'Member active', 'body' => 'Snapshot includes active member.']);
        $firstId = (int) $firstDraft->json('data.id');
        $firstPath = '/api/v1/announcements/'.$firstId.'/publish';

        $missingKey = $this->actingAs($owner)->postJson($firstPath, ['expected_version' => 1]);
        $this->recordApi('D04-10-publish-missing-key', 'owner', 'POST', $firstPath, ['expected_version' => 1], $missingKey);
        $missingKey->assertUnprocessable()->assertJsonValidationErrors('Idempotency-Key');
        $invalidKey = $this->actingAs($owner)->postJson($firstPath, ['expected_version' => 1], ['Idempotency-Key' => 'not-a-uuid']);
        $this->recordApi('D04-10-publish-invalid-key', 'owner', 'POST', $firstPath, ['expected_version' => 1], $invalidKey, ['Idempotency-Key' => 'not-a-uuid']);
        $invalidKey->assertUnprocessable()->assertJsonValidationErrors('Idempotency-Key');

        $memberPublish = $this->actingAs($member)->postJson($firstPath, ['expected_version' => 1], ['Idempotency-Key' => (string) Str::uuid()]);
        $this->recordApi('D04-10-member-publish-forbidden', 'member', 'POST', $firstPath, ['expected_version' => 1], $memberPublish);
        $memberPublish->assertForbidden()->assertJsonPath('code', 'workspace_mutation_forbidden');

        $updated = $this->actingAs($owner)->patchJson('/api/v1/announcements/'.$firstId, ['expected_version' => 1, 'body' => 'Updated before publish.']);
        $updated->assertOk()->assertJsonPath('data.version', 2);
        $stale = $this->publish($firstPath, ['expected_version' => 1], (string) Str::uuid(), 'owner-stale');
        $stale->assertConflict()->assertJsonPath('code', 'stale_version')->assertJsonPath('errors.current_version', 2);
        $this->assertDatabaseMissing('idempotency_receipts', ['canonical_path' => $firstPath]);

        $this->publish($firstPath, ['expected_version' => 2], (string) Str::uuid(), 'owner-active-member')->assertOk();
        $this->assertDatabaseHas('notification_outbox', [
            'user_id' => $member->id,
            'resource_type' => 'announcement',
            'resource_id' => $firstId,
            'event_key' => "announcement:{$firstId}:published",
        ]);

        DB::table('program_members')->where('program_id', $program->id)->where('user_id', $member->id)->update(['status' => 'cancelled', 'left_at' => now('UTC')]);
        $lateDraft = $this->actingAs($owner)->postJson($path, ['title' => 'Member joins later', 'body' => 'No retroactive notification.']);
        $lateId = (int) $lateDraft->json('data.id');
        $latePath = '/api/v1/announcements/'.$lateId.'/publish';
        $latePublished = $this->publish($latePath, ['expected_version' => 1], (string) Str::uuid(), 'owner-no-active-member');
        $latePublished->assertOk();
        $this->assertDatabaseMissing('notification_outbox', ['resource_type' => 'announcement', 'resource_id' => $lateId]);

        DB::table('program_members')->where('program_id', $program->id)->where('user_id', $member->id)->update(['status' => 'active', 'left_at' => null]);
        $joinedLaterRead = $this->actingAs($member)->getJson('/api/v1/announcements/'.$lateId);
        $joinedLaterRead->assertOk()->assertJsonPath('data.state', 'published');
        $this->assertDatabaseMissing('notification_outbox', ['user_id' => $member->id, 'resource_type' => 'announcement', 'resource_id' => $lateId]);

        $paused = $this->program('D04 Fixture Paused');
        $pausedDraft = $this->actingAs($owner)->postJson('/api/v1/programs/'.$paused->id.'/announcements', [
            'title' => 'Publish while paused',
            'body' => 'Contract allows publish in Paused state.',
        ]);
        $pausedId = (int) $pausedDraft->json('data.id');
        $pausedPublish = $this->publish('/api/v1/announcements/'.$pausedId.'/publish', ['expected_version' => 1], (string) Str::uuid(), 'owner-paused-program');
        $pausedPublish->assertOk()->assertJsonPath('data.version', 2);

        $completed = $this->program('D04 Fixture Completed');
        $completedOwner = $this->user('be-d04-other-tutor@tutorlink.test');
        $now = now('UTC')->format('Y-m-d H:i:s.uP');
        $terminalId = DB::table('announcements')->insertGetId([
            'program_id' => $completed->id,
            'author_id' => $completedOwner->id,
            'title' => 'Terminal Program draft',
            'body' => 'Publish must remain blocked.',
            'published_at' => null,
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $terminal = $this->actingAs($completedOwner)->postJson('/api/v1/announcements/'.$terminalId.'/publish', [
            'expected_version' => 1,
        ], ['Idempotency-Key' => (string) Str::uuid()]);
        $this->recordApi('D04-10-terminal-program-conflict', 'other-tutor', 'POST', '/api/v1/announcements/'.$terminalId.'/publish', ['expected_version' => 1], $terminal);
        $terminal->assertConflict()->assertJsonPath('code', 'program_state_conflict');
    }

    public function test_outbox_failure_rolls_back_publish_state_audit_and_receipt(): void
    {
        $program = $this->program('D04 Fixture Active');
        $owner = $this->user('be-d04-owner@tutorlink.test');
        $draft = $this->actingAs($owner)->postJson('/api/v1/programs/'.$program->id.'/announcements', [
            'title' => 'Rollback publish',
            'body' => 'An outbox error must undo every write.',
        ]);
        $announcementId = (int) $draft->json('data.id');
        $path = '/api/v1/announcements/'.$announcementId.'/publish';
        $key = (string) Str::uuid();
        $auditBefore = DB::table('audit_logs')->where('resource_type', 'announcement')->where('resource_id', $announcementId)->count();
        $outboxBefore = DB::table('notification_outbox')->where('resource_type', 'announcement')->where('resource_id', $announcementId)->count();
        $functionName = 'd04_test_fail_announcement_outbox';
        $triggerName = 'd04_test_fail_announcement_outbox';

        DB::unprepared("CREATE FUNCTION {$functionName}() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'D04 injected outbox failure'; END $$");
        DB::unprepared("CREATE TRIGGER {$triggerName} BEFORE INSERT ON notification_outbox FOR EACH ROW WHEN (NEW.type = 'announcement_published') EXECUTE FUNCTION {$functionName}()");

        try {
            $failed = $this->publish($path, ['expected_version' => 1], $key, 'owner-outbox-failure');
            $failed->assertServerError();
            $this->assertDatabaseHas('announcements', [
                'id' => $announcementId,
                'published_at' => null,
                'version' => 1,
            ]);
            $this->assertSame($auditBefore, DB::table('audit_logs')->where('resource_type', 'announcement')->where('resource_id', $announcementId)->count());
            $this->assertSame($outboxBefore, DB::table('notification_outbox')->where('resource_type', 'announcement')->where('resource_id', $announcementId)->count());
            $this->assertDatabaseMissing('idempotency_receipts', [
                'actor_id' => $owner->id,
                'idempotency_key' => $key,
            ]);
        } finally {
            DB::unprepared("DROP TRIGGER IF EXISTS {$triggerName} ON notification_outbox");
            DB::unprepared("DROP FUNCTION IF EXISTS {$functionName}()");
        }
    }

    private function publish(string $path, array $body, string $key, string $label): TestResponse
    {
        $response = $this->actingAs($this->user('be-d04-owner@tutorlink.test'))
            ->postJson($path, $body, ['Idempotency-Key' => $key]);
        $this->recordApi('D04-10-'.$label, 'owner', 'POST', $path, $body, $response, ['Idempotency-Key' => $key]);

        return $response;
    }

    private function program(string $title): Program
    {
        return Program::query()->where('title', $title)->firstOrFail();
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    private function completeAnnouncementFile(User $owner): int
    {
        $bytes = "%PDF-1.4\nsynthetic announcement attachment\n%%EOF";
        $now = now('UTC')->format('Y-m-d H:i:s.uP');

        return DB::table('files')->insertGetId([
            'owner_id' => $owner->id,
            'disk' => 'local',
            'object_key' => 'tests/d04-10-publish-'.Str::uuid().'.pdf',
            'original_name' => 'lesson-plan.pdf',
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

    private function recordApi(string $case, string $actor, string $method, string $path, ?array $body, TestResponse $response, array $requestHeaders = []): void
    {
        fwrite(STDOUT, json_encode([
            'case' => $case,
            'actor' => $actor,
            'request' => ['method' => $method, 'path' => $path, 'headers' => $requestHeaders, 'body' => $body],
            'response' => [
                'status' => $response->getStatusCode(),
                'headers' => ['X-Request-ID' => $response->headers->get('X-Request-ID')],
                'body' => json_decode($response->getContent(), false, 512, JSON_THROW_ON_ERROR),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR).PHP_EOL);
    }
}
