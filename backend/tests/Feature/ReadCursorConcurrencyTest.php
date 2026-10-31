<?php

namespace Tests\Feature;

use App\Actions\Messaging\AdvanceReadCursor;
use App\Actions\TutorProfiles\SuspendTutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\MessageSendFixtures as Fixtures;
use Tests\Support\ProgramApiFixtures;
use Tests\Support\TestActors;
use Tests\TestCase;

class ReadCursorConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    private User $customer;

    private User $tutor;

    private User $admin;

    private int $chat;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Read cursor races require independent PostgreSQL connections.');
        }

        $this->assertSame('read committed', DB::selectOne('SHOW transaction_isolation')->transaction_isolation);
        $this->customer = TestActors::customer();
        $this->tutor = ProgramApiFixtures::eligibleTutor();
        $this->admin = TestActors::admin();
        $this->chat = Fixtures::conversation($this->customer, $this->tutor);
    }

    public function test_racing_reads_keep_the_furthest_timestamp_even_when_its_id_is_smaller(): void
    {
        $newer = Fixtures::seed($this->chat, $this->tutor, ['created_at' => '2026-10-28 07:00:00.900001+00']);
        $older = Fixtures::seed($this->chat, $this->tutor, ['created_at' => '2026-10-28 07:00:00.100001+00']);
        $results = $this->raceReads([[$this->customer->id, $older], [$this->customer->id, $newer]]);
        foreach ($results as $result) {
            $this->assertSame(200, $result['status']);
            $this->assertContains($result['id'], [$newer, $older]);
        }
        $this->assertMarker($this->customer, $newer);
    }

    public function test_racing_equal_timestamp_targets_keep_the_larger_message_id(): void
    {
        $older = Fixtures::seed($this->chat, $this->tutor);
        $newer = Fixtures::seed($this->chat, $this->tutor);
        $this->raceReads([[$this->customer->id, $newer], [$this->customer->id, $older]]);
        $this->assertMarker($this->customer, $newer);
    }

    public function test_two_stale_reads_cannot_reduce_an_existing_newer_marker_or_change_its_time(): void
    {
        $first = Fixtures::seed($this->chat, $this->tutor);
        $second = Fixtures::seed($this->chat, $this->tutor);
        $third = Fixtures::seed($this->chat, $this->tutor);
        DB::table('conversation_members')->where('conversation_id', $this->chat)->where('user_id', $this->customer->id)
            ->update(['last_read_message_id' => $third, 'last_read_at' => '2026-10-28 06:00:00+00']);
        $before = DB::table('conversation_members')->where('conversation_id', $this->chat)->where('user_id', $this->customer->id)->value('last_read_at');
        $results = $this->raceReads([[$this->customer->id, $first], [$this->customer->id, $second]]);
        foreach ($results as $result) {
            $this->assertSame($third, $result['id']);
        }
        $this->assertMarker($this->customer, $third);
        $this->assertSame($before, DB::table('conversation_members')->where('conversation_id', $this->chat)->where('user_id', $this->customer->id)->value('last_read_at'));
    }

    public function test_two_members_update_only_their_own_markers_when_they_race(): void
    {
        $first = Fixtures::seed($this->chat, $this->tutor);
        $second = Fixtures::seed($this->chat, $this->customer);
        $this->raceReads([[$this->customer->id, $second], [$this->tutor->id, $first]], true);
        $this->assertMarker($this->customer, $second);
        $this->assertMarker($this->tutor, $first);
    }

    public function test_suspension_committing_before_a_waiting_read_blocks_a_stale_actor(): void
    {
        $target = Fixtures::seed($this->chat, $this->customer);
        $process = null;
        DB::beginTransaction();
        try {
            app(SuspendTutorProfile::class)->execute($this->admin, $this->tutor->tutorProfile->id, true, 'Read cursor race suspension.');
            $process = $this->startWorker(['mode' => 'read', 'actor' => $this->tutor->id, 'message' => $target]);
            $this->waitForBlock($process);
            DB::commit();
            $result = $this->workerResult($process);
            $this->assertSame(403, $result['status']);
            $this->assertSame('account_inactive', $result['code']);
            $this->assertDatabaseHas('conversation_members', ['conversation_id' => $this->chat, 'user_id' => $this->tutor->id, 'last_read_message_id' => null]);
        } finally {
            $this->cleanup($process === null ? [] : [$process]);
        }
    }

    public function test_a_read_committing_first_is_preserved_when_suspension_then_completes(): void
    {
        $target = Fixtures::seed($this->chat, $this->customer);
        $process = null;
        DB::beginTransaction();
        try {
            app(AdvanceReadCursor::class)->execute($this->tutor, $this->chat, $target, (string) Str::uuid());
            $process = $this->startWorker(['mode' => 'suspend', 'actor' => $this->admin->id, 'profile' => $this->tutor->tutorProfile->id]);
            $this->waitForBlock($process);
            DB::commit();
            $this->assertSame(200, $this->workerResult($process)['status']);
            $this->assertSame('suspended', $this->tutor->fresh()->status);
            $this->assertMarker($this->tutor, $target);
            $this->actingAs($this->tutor->fresh())->patchJson('/api/v1/conversations/'.$this->chat.'/read', ['last_read_message_id' => $target])->assertForbidden();
        } finally {
            $this->cleanup($process === null ? [] : [$process]);
        }
    }

    public function test_a_send_waiting_for_read_commit_stays_unread_after_the_target(): void
    {
        $target = Fixtures::seed($this->chat, $this->tutor);
        $process = null;
        DB::beginTransaction();
        try {
            app(AdvanceReadCursor::class)->execute($this->customer, $this->chat, $target, (string) Str::uuid());
            $process = $this->startWorker(['mode' => 'send', 'actor' => $this->tutor->id, 'payload' => Fixtures::payload('Arrived after read target.')]);
            $this->waitForBlock($process);
            DB::commit();
            $this->assertSame(201, $this->workerResult($process)['status']);
            $this->assertMarker($this->customer, $target);
            $this->actingAs($this->customer)->getJson('/api/v1/conversations')->assertOk()->assertJsonPath('data.0.unread_count', 1);
        } finally {
            $this->cleanup($process === null ? [] : [$process]);
        }
    }

    /** @param list<array{int,int}> $requests
     * @return list<array<string,mixed>>
     */
    private function raceReads(array $requests, bool $holdConversation = false): array
    {
        $processes = [];
        DB::beginTransaction();
        try {
            if ($holdConversation) {
                DB::table('conversations')->where('id', $this->chat)->lockForUpdate()->first();
            } else {
                DB::table('users')->where('id', $this->customer->id)->lockForUpdate()->first();
            }
            foreach ($requests as [$actor, $message]) {
                $process = $this->startWorker(['mode' => 'read', 'actor' => $actor, 'message' => $message]);
                $processes[] = $process;
                $this->waitForBlock($process);
            }
            DB::commit();

            return array_map(fn (Process $process): array => $this->workerResult($process), $processes);
        } finally {
            $this->cleanup($processes);
        }
    }

    private function assertMarker(User $actor, int $message): void
    {
        $this->assertDatabaseHas('conversation_members', ['conversation_id' => $this->chat, 'user_id' => $actor->id, 'last_read_message_id' => $message]);
    }

    /** @param array<string,mixed> $data */
    private function startWorker(array $data): Process
    {
        $data['conversation'] = $this->chat;
        $script = <<<'PHP'
            require 'vendor/autoload.php';
            $app = require 'bootstrap/app.php';
            $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
            \Tests\Support\TestDatabaseGuard::assertSafe($app['config']);
            $db = \Illuminate\Support\Facades\DB::connection();
            if ($db->getDriverName() !== 'pgsql' || $db->getDatabaseName() !== $argv[1]) { exit(2); }
            $data = json_decode($argv[2], true, 32, JSON_THROW_ON_ERROR);
            $actor = \App\Models\User::findOrFail($data['actor']);
            $pid = $db->selectOne('SELECT pg_backend_pid() AS pid')->pid;
            fwrite(STDOUT, "READY:{$pid}\n");
            fflush(STDOUT);
            try {
                if ($data['mode'] === 'read') {
                    $member = app(\App\Actions\Messaging\AdvanceReadCursor::class)->execute($actor, $data['conversation'], $data['message'], (string) \Illuminate\Support\Str::uuid());
                    $result = ['status' => 200, 'id' => (int) $member->last_read_message_id];
                } elseif ($data['mode'] === 'send') {
                    $sent = app(\App\Actions\Messaging\SendTextMessage::class)->execute($actor, $data['conversation'], $data['payload'], (string) \Illuminate\Support\Str::uuid());
                    $result = ['status' => $sent['created'] ? 201 : 200];
                } else {
                    app(\App\Actions\TutorProfiles\SuspendTutorProfile::class)->execute($actor, $data['profile'], true, 'Read cursor race suspension.');
                    $result = ['status' => 200];
                }
            } catch (\Illuminate\Http\Exceptions\HttpResponseException $exception) {
                $response = $exception->getResponse();
                $body = json_decode($response->getContent(), true, 32, JSON_THROW_ON_ERROR);
                $result = ['status' => $response->getStatusCode(), 'code' => $body['code'] ?? null];
            } catch (\Throwable $exception) {
                fwrite(STDERR, get_class($exception).': '.$exception->getMessage());
                exit(3);
            }
            fwrite(STDOUT, 'RESULT:'.json_encode($result, JSON_THROW_ON_ERROR)."\n");
            PHP;
        $process = new Process([PHP_BINARY, '-r', $script, DB::connection()->getDatabaseName(), json_encode($data, JSON_THROW_ON_ERROR)], base_path(), ['APP_ENV' => 'testing']);
        $process->setTimeout(20);
        $process->start();

        return $process;
    }

    private function waitForBlock(Process $process): void
    {
        $deadline = microtime(true) + 10;
        do {
            if (preg_match('/READY:(\d+)/', $process->getOutput(), $match)) {
                $row = DB::selectOne('SELECT cardinality(pg_blocking_pids(?)) AS blockers', [(int) $match[1]]);
                if ((int) $row->blockers > 0) {
                    return;
                }
            }
            if (! $process->isRunning()) {
                $this->fail('Worker exited before waiting: '.$process->getOutput().$process->getErrorOutput());
            }
            usleep(50_000);
        } while (microtime(true) < $deadline);
        $this->fail('Worker did not wait for a PostgreSQL lock: '.$process->getOutput().$process->getErrorOutput());
    }

    /** @return array<string,mixed> */
    private function workerResult(Process $process): array
    {
        $process->wait();
        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $this->assertSame(1, preg_match('/RESULT:(\{[^\r\n]+\})/', $process->getOutput(), $match));

        return json_decode($match[1], true, 32, JSON_THROW_ON_ERROR);
    }

    /** @param list<Process> $processes */
    private function cleanup(array $processes): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop(0, 9);
            }
        }
    }
}
