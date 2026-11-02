<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BeD04OverviewApiTest extends TestCase
{
    public function test_active_overview_uses_stable_summaries_and_allowlisted_projections(): void
    {
        $program = $this->program('D04 Fixture Active');
        $owner = $this->user('be-d04-owner@tutorlink.test');
        $member = $this->user('be-d04-late-member@tutorlink.test');
        $now = CarbonImmutable::parse('2026-11-02T03:00:00.123456Z');
        $insertedSessionIds = [];
        $progressBefore = DB::table('progress_records')
            ->where('program_id', $program->id)
            ->whereIn('note', ['D04_FIXTURE_PROGRESS_001', 'D04_FIXTURE_PROGRESS_002'])
            ->get(['id', 'recorded_at', 'updated_at'])
            ->keyBy('id');
        $upcoming = DB::table('program_sessions')
            ->where('program_id', $program->id)
            ->where('title', 'D04 Fixture Upcoming Session')
            ->first(['id', 'planned_starts_at']);

        $this->assertCount(2, $progressBefore);
        $this->assertNotNull($upcoming);

        try {
            Carbon::setTestNow($now);
            DB::table('progress_records')->where('note', 'D04_FIXTURE_PROGRESS_001')->update([
                'recorded_at' => '2026-10-31 12:00:00+00:00',
                'updated_at' => '2026-10-31 12:01:00+00:00',
            ]);
            DB::table('progress_records')->where('note', 'D04_FIXTURE_PROGRESS_002')->update([
                'recorded_at' => '2026-10-30 12:00:00+00:00',
                'updated_at' => '2026-11-01 23:59:00+00:00',
            ]);
            DB::table('program_sessions')->where('id', $upcoming->id)->update([
                'planned_starts_at' => $this->timestamp($now->addHours(5)),
            ]);

            foreach ([
                ['Earlier future tie', $now->addHour()],
                ['Later future tie', $now->addHour()],
                ['Exactly now', $now],
                ['Past scheduled', $now->subSecond()],
            ] as $index => [$title, $startsAt]) {
                $insertedSessionIds[] = DB::table('program_sessions')->insertGetId([
                    'program_id' => $program->id,
                    'program_version' => 1,
                    'sequence_number' => 101 + $index,
                    'title' => 'D04 Overview '.$title,
                    'planned_starts_at' => $this->timestamp($startsAt),
                    'planned_ends_at' => $this->timestamp($startsAt->addHour()),
                    'timezone' => 'Asia/Ho_Chi_Minh',
                    'delivery_mode' => 'online',
                    'location_text' => null,
                    'meeting_url' => null,
                    'status' => 'scheduled',
                    'created_at' => $this->timestamp($now),
                    'updated_at' => $this->timestamp($now),
                ]);
            }

            $response = $this->actingAs($owner)->getJson($this->overviewPath($program));
            $this->recordApi('D04-12-active-owner-overview', 'owner', 'GET', $this->overviewPath($program), null, $response);
            $response->assertOk();

            $data = $response->json('data');
            $this->assertSame(['next_session', 'task_counts', 'latest_progress', 'latest_announcement', 'capabilities'], array_keys($data));
            $this->assertSame(min($insertedSessionIds[0], $insertedSessionIds[1]), $data['next_session']['id']);
            $this->assertSame('D04 Overview Earlier future tie', $data['next_session']['title']);
            $this->assertSame(1, $data['next_session']['version']);
            $this->assertSame([
                'id', 'program_id', 'program_version', 'sequence_number', 'title',
                'planned_starts_at', 'planned_ends_at', 'timezone', 'delivery_mode',
                'location_text', 'meeting_url', 'status', 'version', 'created_at',
                'updated_at', 'actual',
            ], array_keys($data['next_session']));
            $this->assertSame([
                'todo' => 1,
                'in_progress' => 1,
                'submitted' => 1,
                'completed' => 1,
                'cancelled' => 1,
            ], $data['task_counts']);

            $this->assertSame('D04_FIXTURE_PROGRESS_001', $data['latest_progress']['note']);
            $this->assertSame([
                'id', 'program_id', 'customer_id', 'metric_name', 'value', 'note',
                'recorded_by', 'recorded_at', 'version', 'created_at', 'updated_at',
            ], array_keys($data['latest_progress']));
            $this->assertSame('D04 Fixture Published Tie B', $data['latest_announcement']['title']);
            $this->assertSame([
                'id', 'program_id', 'author_id', 'state', 'title', 'body', 'files',
                'published_at', 'version', 'created_at', 'updated_at',
            ], array_keys($data['latest_announcement']));
            $this->assertSame('published', $data['latest_announcement']['state']);
            $this->assertSame([
                'can_create_session' => true,
                'can_create_task' => true,
                'can_record_progress' => true,
                'can_create_fee_record' => true,
                'can_create_announcement' => true,
                'can_create_review' => false,
            ], $data['capabilities']);

            $memberResponse = $this->actingAs($member)->getJson($this->overviewPath($program));
            $this->recordApi('D04-12-active-member-overview', 'member', 'GET', $this->overviewPath($program), null, $memberResponse);
            $memberResponse->assertOk()
                ->assertJsonPath('data.latest_announcement.state', 'published')
                ->assertJsonPath('data.capabilities.can_create_session', false)
                ->assertJsonPath('data.capabilities.can_record_progress', false)
                ->assertJsonPath('data.capabilities.can_create_review', false);
        } finally {
            Carbon::setTestNow();
            if ($insertedSessionIds !== []) {
                DB::table('program_sessions')->whereIn('id', $insertedSessionIds)->delete();
            }
            foreach ($progressBefore as $id => $row) {
                DB::table('progress_records')->where('id', $id)->update([
                    'recorded_at' => $row->recorded_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
            if ($upcoming !== null) {
                DB::table('program_sessions')->where('id', $upcoming->id)->update([
                    'planned_starts_at' => $upcoming->planned_starts_at,
                ]);
            }
        }
    }

    public function test_empty_and_terminal_overview_returns_nulls_zero_counts_and_role_capabilities(): void
    {
        $cancelled = $this->program('D04 Fixture Cancelled');
        $owner = $this->user('be-d04-other-tutor@tutorlink.test');
        $member = $this->user('be-d04-member@tutorlink.test');
        $ownerResponse = $this->actingAs($owner)->getJson($this->overviewPath($cancelled));
        $this->recordApi('D04-12-empty-cancelled-owner', 'owner', 'GET', $this->overviewPath($cancelled), null, $ownerResponse);
        $ownerResponse->assertOk()->assertExactJson([
            'data' => [
                'next_session' => null,
                'task_counts' => ['todo' => 0, 'in_progress' => 0, 'submitted' => 0, 'completed' => 0, 'cancelled' => 0],
                'latest_progress' => null,
                'latest_announcement' => null,
                'capabilities' => [
                    'can_create_session' => false,
                    'can_create_task' => false,
                    'can_record_progress' => false,
                    'can_create_fee_record' => false,
                    'can_create_announcement' => false,
                    'can_create_review' => false,
                ],
            ],
        ]);

        $memberResponse = $this->actingAs($member)->getJson($this->overviewPath($cancelled));
        $this->recordApi('D04-12-empty-cancelled-member', 'member', 'GET', $this->overviewPath($cancelled), null, $memberResponse);
        $memberResponse->assertOk()->assertJsonPath('data.latest_progress', null);

        $completed = $this->program('D04 Fixture Completed');
        $completedOwner = $this->actingAs($owner)->getJson($this->overviewPath($completed));
        $this->recordApi('D04-12-completed-owner-capabilities', 'owner', 'GET', $this->overviewPath($completed), null, $completedOwner);
        $completedOwner->assertOk()
            ->assertJsonPath('data.capabilities.can_create_session', false)
            ->assertJsonPath('data.capabilities.can_record_progress', false)
            ->assertJsonPath('data.capabilities.can_create_review', false);

        $reviewEligibleMember = $this->actingAs($member)->getJson($this->overviewPath($completed));
        $this->recordApi('D04-12-completed-customer-capabilities', 'member', 'GET', $this->overviewPath($completed), null, $reviewEligibleMember);
        $reviewEligibleMember->assertOk()
            ->assertJsonPath('data.capabilities.can_create_session', false)
            ->assertJsonPath('data.capabilities.can_record_progress', false)
            ->assertJsonPath('data.capabilities.can_create_review', true);

        $this->assertFalse(DB::table('reviews')->where('program_id', $completed->id)->where('customer_id', $member->id)->exists());
        $reviewId = DB::table('reviews')->insertGetId([
            'program_id' => $completed->id,
            'customer_id' => $member->id,
            'tutor_profile_id' => $completed->owner_tutor_profile_id,
            'rating' => 5,
            'comment' => 'Synthetic capability fixture.',
            'created_at' => '2026-11-02 00:00:00+00:00',
            'updated_at' => '2026-11-02 00:00:00+00:00',
        ]);
        try {
            $alreadyReviewed = $this->actingAs($member)->getJson($this->overviewPath($completed));
            $this->recordApi('D04-12-completed-customer-already-reviewed', 'member', 'GET', $this->overviewPath($completed), null, $alreadyReviewed);
            $alreadyReviewed->assertOk()->assertJsonPath('data.capabilities.can_create_review', false);
        } finally {
            DB::table('reviews')->where('id', $reviewId)->delete();
        }

        $paused = $this->program('D04 Fixture Paused');
        $pausedOwner = $this->actingAs($this->user('be-d04-owner@tutorlink.test'))->getJson($this->overviewPath($paused));
        $this->recordApi('D04-12-paused-owner-capabilities', 'owner', 'GET', $this->overviewPath($paused), null, $pausedOwner);
        $pausedOwner->assertOk()
            ->assertJsonPath('data.capabilities.can_create_session', false)
            ->assertJsonPath('data.capabilities.can_create_task', false)
            ->assertJsonPath('data.capabilities.can_record_progress', true)
            ->assertJsonPath('data.capabilities.can_create_fee_record', false)
            ->assertJsonPath('data.capabilities.can_create_announcement', false);
    }

    public function test_overview_checks_scope_authentication_and_rejects_query_parameters(): void
    {
        $program = $this->program('D04 Fixture Active');
        $path = $this->overviewPath($program);

        $this->getJson($path)->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
        $outsider = $this->actingAs($this->user('be-d04-outsider@tutorlink.test'))->getJson($path);
        $this->recordApi('D04-12-outsider-hidden', 'outsider', 'GET', $path, null, $outsider);
        $outsider->assertNotFound()->assertJsonPath('code', 'program_not_found');

        $inactive = $this->actingAs($this->user('be-d04-inactive@tutorlink.test'))->getJson($path);
        $this->recordApi('D04-12-inactive-account', 'inactive', 'GET', $path, null, $inactive);
        $inactive->assertForbidden()->assertJsonPath('code', 'account_inactive');

        $admin = $this->actingAs($this->user('be-d04-admin@tutorlink.test'))->getJson($path);
        $this->recordApi('D04-12-admin-hidden', 'admin', 'GET', $path, null, $admin);
        $admin->assertNotFound()->assertJsonPath('code', 'program_not_found');

        $unknownQuery = $this->actingAs($this->user('be-d04-owner@tutorlink.test'))->getJson($path.'?unexpected=1');
        $this->recordApi('D04-12-unknown-query', 'owner', 'GET', $path.'?unexpected=1', null, $unknownQuery);
        $unknownQuery->assertUnprocessable()->assertJsonPath('code', 'validation_failed')->assertJsonValidationErrors('unexpected');
    }

    public function test_overview_reads_program_and_summaries_from_one_repeatable_snapshot(): void
    {
        $program = $this->program('D04 Fixture Active');
        $owner = $this->user('be-d04-owner@tutorlink.test');
        $task = DB::table('tasks')->where('program_id', $program->id)->where('status', 'todo')->first(['id', 'status']);
        $this->assertNotNull($task);

        $connectionName = 'd04_overview_snapshot_probe';
        config(['database.connections.'.$connectionName => config('database.connections.'.DB::getDefaultConnection())]);
        DB::purge($connectionName);
        $probe = DB::connection($connectionName);
        $this->assertSame('tutorlink_test', $probe->getDatabaseName());
        $originalProgramStatus = $probe->table('programs')->where('id', $program->id)->value('status');
        $didUpdate = false;
        $armed = true;

        DB::listen(function (QueryExecuted $query) use ($program, $task, $probe, &$armed, &$didUpdate): void {
            if (! $armed || $didUpdate || $query->connectionName !== DB::getDefaultConnection()) {
                return;
            }

            $sql = strtolower($query->sql);
            if (! str_contains($sql, 'from "programs"') && ! str_contains($sql, 'from programs')) {
                return;
            }

            $probe->transaction(function () use ($probe, $program, $task): void {
                $probe->table('programs')->where('id', $program->id)->update(['status' => 'paused']);
                $probe->table('tasks')->where('id', $task->id)->update(['status' => 'completed']);
            });
            $didUpdate = true;
        });

        try {
            $response = $this->actingAs($owner)->getJson($this->overviewPath($program));
            $this->recordApi('D04-12-repeatable-snapshot-concurrent-change', 'owner', 'GET', $this->overviewPath($program), null, $response);
            $response->assertOk()
                ->assertJsonPath('data.task_counts.todo', 1)
                ->assertJsonPath('data.task_counts.completed', 1)
                ->assertJsonPath('data.capabilities.can_create_session', true);
            $this->assertTrue($didUpdate, 'The second connection must commit a Program/task change after the overview snapshot starts.');
            $this->assertSame('paused', $probe->table('programs')->where('id', $program->id)->value('status'));
            $this->assertSame('completed', $probe->table('tasks')->where('id', $task->id)->value('status'));
        } finally {
            $armed = false;
            if ($didUpdate) {
                $probe->table('programs')->where('id', $program->id)->update(['status' => $originalProgramStatus]);
                $probe->table('tasks')->where('id', $task->id)->update(['status' => 'todo']);
            }
            DB::purge($connectionName);
        }
    }

    private function program(string $title): Program
    {
        return Program::query()->where('title', $title)->firstOrFail();
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    private function overviewPath(Program $program): string
    {
        return '/api/v1/programs/'.$program->id.'/overview';
    }

    private function timestamp(CarbonImmutable $value): string
    {
        return $value->format('Y-m-d H:i:s.uP');
    }

    /** @param array<string, mixed>|null $body */
    private function recordApi(string $case, string $actor, string $method, string $path, ?array $body, TestResponse $response): void
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
