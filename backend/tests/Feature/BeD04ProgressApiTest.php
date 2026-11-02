<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BeD04ProgressApiTest extends TestCase
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

    public function test_owner_creates_progress_and_member_reads_stable_paginated_list(): void
    {
        $program = $this->program('D04 Fixture Active');
        $owner = $this->user('be-d04-owner@tutorlink.test');
        $auditCount = DB::table('audit_logs')->where('resource_type', 'progress_record')->count();
        $outboxCount = DB::table('notification_outbox')->count();
        $response = $this->actingAs($owner)->postJson($this->programPath($program), [
            'metric_name' => 'progress_percent',
            'value' => 35,
        ]);
        $this->recordApi('D04-07-POST-create', 'owner', 'POST', $this->programPath($program), [
            'metric_name' => 'progress_percent', 'value' => 35,
        ], $response);

        $response
            ->assertCreated()
            ->assertJsonPath('data.program_id', $program->id)
            ->assertJsonPath('data.customer_id', $program->customer_id)
            ->assertJsonPath('data.metric_name', 'progress_percent')
            ->assertJsonPath('data.value', 35)
            ->assertJsonPath('data.note', null)
            ->assertJsonPath('data.recorded_by', $owner->id)
            ->assertJsonPath('data.version', 1);
        $createdId = (int) $response->json('data.id');
        $tieId = DB::table('progress_records')->insertGetId([
            'program_id' => $program->id,
            'customer_id' => $program->customer_id,
            'metric_name' => 'progress_percent',
            'value' => 36,
            'note' => 'same recorded_at tie fixture',
            'recorded_by' => $owner->id,
            'recorded_at' => $response->json('data.recorded_at'),
            'version' => 1,
            'created_at' => $response->json('data.created_at'),
            'updated_at' => $response->json('data.updated_at'),
        ]);
        $this->assertSame([
            'id', 'program_id', 'customer_id', 'metric_name', 'value', 'note', 'recorded_by',
            'recorded_at', 'version', 'created_at', 'updated_at',
        ], array_keys($response->json('data')));
        $this->assertSame($auditCount, DB::table('audit_logs')->where('resource_type', 'progress_record')->count());
        $this->assertSame($outboxCount, DB::table('notification_outbox')->count());

        $member = $this->user('be-d04-late-member@tutorlink.test');
        $list = $this->actingAs($member)->getJson($this->programPath($program).'?per_page=1');
        $this->recordApi('D04-07-GET-list-member', 'member', 'GET', $this->programPath($program).'?per_page=1', null, $list);
        $list
            ->assertOk()
            ->assertJsonPath('data.0.id', $tieId)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 57)
            ->assertJsonPath('meta.last_page', 57);
        $this->assertGreaterThan($createdId, $tieId, 'Equal recorded_at values are ordered by descending ID.');

        $invalidPageSize = $this->getJson($this->programPath($program).'?per_page=51');
        $this->recordApi('D04-07-GET-per-page-max', 'member', 'GET', $this->programPath($program).'?per_page=51', null, $invalidPageSize);
        $invalidPageSize->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }

    public function test_patch_noop_null_and_stale_version_have_expected_side_effects(): void
    {
        $program = $this->program('D04 Fixture Active');
        $owner = $this->user('be-d04-owner@tutorlink.test');
        $record = DB::table('progress_records')
            ->where('program_id', $program->id)
            ->where('note', 'D04_FIXTURE_PROGRESS_001')
            ->first();
        $this->assertNotNull($record);
        $originalRecordedAt = $record->recorded_at;
        $originalRecordedBy = (int) $record->recorded_by;
        $outboxBefore = DB::table('notification_outbox')->count();
        $path = '/api/v1/progress-records/'.$record->id;

        $updated = $this->actingAs($owner)->patchJson($path, ['expected_version' => 1, 'value' => 44]);
        $this->recordApi('D04-07-PATCH-update-omit-note', 'owner', 'PATCH', $path, [
            'expected_version' => 1, 'value' => 44,
        ], $updated);
        $updated
            ->assertOk()
            ->assertJsonPath('data.value', 44)
            ->assertJsonPath('data.note', 'D04_FIXTURE_PROGRESS_001')
            ->assertJsonPath('data.version', 2);
        $this->assertSame($originalRecordedAt, DB::table('progress_records')->where('id', $record->id)->value('recorded_at'));
        $this->assertSame($originalRecordedBy, (int) DB::table('progress_records')->where('id', $record->id)->value('recorded_by'));
        $this->assertSame($outboxBefore, DB::table('notification_outbox')->count());

        $auditBeforeNoop = DB::table('audit_logs')->where('resource_type', 'progress_record')->where('resource_id', $record->id)->count();
        $updatedAt = DB::table('progress_records')->where('id', $record->id)->value('updated_at');
        $noop = $this->patchJson($path, ['expected_version' => 2, 'value' => 44]);
        $this->recordApi('D04-07-PATCH-noop', 'owner', 'PATCH', $path, ['expected_version' => 2, 'value' => 44], $noop);
        $noop->assertOk()->assertJsonPath('data.version', 2);
        $this->assertSame($updatedAt, DB::table('progress_records')->where('id', $record->id)->value('updated_at'));
        $this->assertSame($auditBeforeNoop, DB::table('audit_logs')->where('resource_type', 'progress_record')->where('resource_id', $record->id)->count());

        $requestId = 'c7b5a4d1-1201-4b93-9edb-38c45fa9b4df';
        $cleared = $this->patchJson($path, ['expected_version' => 2, 'note' => null], ['X-Request-ID' => $requestId]);
        $this->recordApi('D04-07-PATCH-null-note', 'owner', 'PATCH', $path, ['expected_version' => 2, 'note' => null], $cleared);
        $cleared->assertOk()->assertJsonPath('data.note', null)->assertJsonPath('data.version', 3);
        $this->assertSame($auditBeforeNoop + 1, DB::table('audit_logs')->where('resource_type', 'progress_record')->where('resource_id', $record->id)->count());
        $this->assertSame($outboxBefore, DB::table('notification_outbox')->count());

        $staleSameValue = $this->patchJson($path, ['expected_version' => 2, 'note' => null]);
        $this->recordApi('D04-07-PATCH-stale-same-value', 'owner', 'PATCH', $path, ['expected_version' => 2, 'note' => null], $staleSameValue);
        $staleSameValue->assertConflict()->assertJsonPath('code', 'stale_version')->assertJsonPath('errors.current_version', 3);

        $audit = DB::table('audit_logs')
            ->where('resource_type', 'progress_record')
            ->where('resource_id', $record->id)
            ->orderByDesc('id')
            ->first();
        $this->assertSame('progress_record.update', $audit->action);
        $this->assertSame($requestId, $audit->request_id);
        $after = json_decode($audit->after, true, 512, JSON_THROW_ON_ERROR);
        $this->assertEqualsCanonicalizing(['program_id', 'metric_name', 'value', 'version', 'note_present', 'note_length', 'note_sha256'], array_keys($after));
        $this->assertFalse($after['note_present']);
        $this->assertNull($after['note_sha256']);
    }

    public function test_invalid_values_permissions_and_program_state_are_enforced(): void
    {
        $program = $this->program('D04 Fixture Active');
        $owner = $this->user('be-d04-owner@tutorlink.test');
        $path = $this->programPath($program);

        foreach ([['metric_name' => 'hours', 'value' => 20], ['metric_name' => 'progress_percent', 'value' => 101], ['metric_name' => 'progress_percent', 'value' => '20'], ['metric_name' => 'progress_percent', 'value' => 20, 'note' => '   ']] as $index => $body) {
            $response = $this->actingAs($owner)->postJson($path, $body);
            $this->recordApi('D04-07-POST-invalid-'.$index, 'owner', 'POST', $path, $body, $response);
            $response->assertUnprocessable()->assertJsonPath('code', 'validation_failed');
        }

        $floatJson = '{"metric_name":"progress_percent","value":20.0}';
        $floatResponse = $this->actingAs($owner)->call('POST', $path, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $floatJson);
        $this->recordApi('D04-07-POST-invalid-float', 'owner', 'POST', $path, $floatJson, $floatResponse);
        $floatResponse->assertUnprocessable()->assertJsonValidationErrors('value');

        $member = $this->user('be-d04-late-member@tutorlink.test');
        $memberWrite = $this->actingAs($member)->postJson($path, ['metric_name' => 'progress_percent', 'value' => 45]);
        $this->recordApi('D04-07-POST-member-forbidden', 'member', 'POST', $path, ['metric_name' => 'progress_percent', 'value' => 45], $memberWrite);
        $memberWrite->assertForbidden()->assertJsonPath('code', 'workspace_mutation_forbidden');

        $outsider = $this->user('be-d04-outsider@tutorlink.test');
        $outsiderRead = $this->actingAs($outsider)->getJson($path);
        $this->recordApi('D04-07-GET-outsider-hidden', 'outsider', 'GET', $path, null, $outsiderRead);
        $outsiderRead->assertNotFound()->assertJsonPath('code', 'program_not_found');

        $paused = $this->program('D04 Fixture Paused');
        $pausedWrite = $this->actingAs($owner)->postJson($this->programPath($paused), ['metric_name' => 'progress_percent', 'value' => 66]);
        $this->recordApi('D04-07-POST-paused-allowed', 'owner', 'POST', $this->programPath($paused), ['metric_name' => 'progress_percent', 'value' => 66], $pausedWrite);
        $pausedWrite->assertCreated();

        $completed = $this->program('D04 Fixture Completed');
        $otherTutor = $this->user('be-d04-other-tutor@tutorlink.test');
        $terminalWrite = $this->actingAs($otherTutor)->postJson($this->programPath($completed), ['metric_name' => 'progress_percent', 'value' => 66]);
        $this->recordApi('D04-07-POST-terminal-conflict', 'owner', 'POST', $this->programPath($completed), ['metric_name' => 'progress_percent', 'value' => 66], $terminalWrite);
        $terminalWrite->assertConflict()->assertJsonPath('code', 'program_state_conflict');
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
        return '/api/v1/programs/'.$program->id.'/progress-records';
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
