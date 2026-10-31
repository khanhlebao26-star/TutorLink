<?php

namespace Tests\Feature;

use App\Actions\Messaging\SendTextMessage;
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

class SendTextMessageConcurrencyTest extends TestCase
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
            $this->markTestSkipped('Message races require independent PostgreSQL connections.');
        }

        $this->assertSame('read committed', DB::selectOne('SHOW transaction_isolation')->transaction_isolation);
        $this->customer = TestActors::customer();
        $this->tutor = ProgramApiFixtures::eligibleTutor();
        $this->admin = TestActors::admin();
        $this->chat = Fixtures::conversation($this->customer, $this->tutor);
    }

    public function test_concurrent_retries_create_one_message_and_return_201_and_200(): void
    {
        $payload = Fixtures::payload("a\r\nb");
        $retry = array_replace($payload, ['body' => "a\nb"]);
        $results = $this->raceSends([[$this->chat, $payload], [$this->chat, $retry]]);
        $this->assertStatuses([200, 201], $results);
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_concurrent_different_payloads_with_one_key_return_one_conflict(): void
    {
        $payload = Fixtures::payload('first');
        $results = $this->raceSends([[$this->chat, $payload], [$this->chat, array_replace($payload, ['body' => 'second'])]]);
        $this->assertStatuses([201, 409], $results);
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_same_sender_key_cannot_be_created_in_two_conversations_concurrently(): void
    {
        $otherChat = Fixtures::conversation($this->customer, ProgramApiFixtures::eligibleTutor());
        $payload = Fixtures::payload();
        $results = $this->raceSends([[$this->chat, $payload], [$otherChat, $payload]]);
        $this->assertStatuses([201, 409], $results);
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_only_one_request_can_take_the_last_global_quota_slot(): void
    {
        $otherChat = Fixtures::conversation($this->customer, ProgramApiFixtures::eligibleTutor());
        for ($i = 0; $i < 29; $i++) {
            Fixtures::seed($this->chat, $this->customer);
        }
        $results = $this->raceSends([[$this->chat, Fixtures::payload()], [$otherChat, Fixtures::payload()]]);
        $this->assertStatuses([201, 429], $results);
        $this->assertDatabaseCount('messages', 30);
    }

    public function test_send_waiting_for_committed_h02_suspension_is_rejected(): void
    {
        $process = null;
        DB::beginTransaction();
        try {
            app(SuspendTutorProfile::class)->execute($this->admin, $this->tutor->tutorProfile->id, true, 'Race test suspension.');
            $process = $this->startWorker(['mode' => 'send', 'actor' => $this->customer->id, 'conversation' => $this->chat, 'payload' => Fixtures::payload()]);
            $this->waitForBlock($process);
            DB::commit();
            $result = $this->workerResult($process);
            $this->assertSame(409, $result['status']);
            $this->assertSame('peer_unavailable', $result['code']);
            $this->assertDatabaseCount('messages', 0);
            $this->assertSame('suspended', $this->tutor->fresh()->status);
        } finally {
            $this->cleanup($process === null ? [] : [$process]);
        }
    }

    public function test_send_commits_before_waiting_h02_suspension_and_remains_visible_to_polling(): void
    {
        $url = '/api/v1/conversations/'.$this->chat.'/messages';
        $origin = $this->actingAs($this->customer)->getJson($url)->assertOk()->json('meta.polling_cursor');
        $payload = Fixtures::payload();
        $process = null;
        DB::beginTransaction();
        try {
            $sent = app(SendTextMessage::class)->execute($this->customer, $this->chat, $payload, (string) Str::uuid());
            $process = $this->startWorker(['mode' => 'suspend', 'actor' => $this->admin->id, 'profile' => $this->tutor->tutorProfile->id]);
            $this->waitForBlock($process);
            DB::commit();
            $this->assertSame(200, $this->workerResult($process)['status']);
            $this->assertDatabaseCount('messages', 1);
            $this->assertSame('suspended', $this->tutor->fresh()->status);
            $this->getJson($url.'?'.http_build_query(['after' => $origin]))->assertOk()
                ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', (int) $sent['message']->id);
            $this->postJson($url, $payload)->assertOk()->assertJsonPath('data.id', (int) $sent['message']->id);
        } finally {
            $this->cleanup($process === null ? [] : [$process]);
        }
    }

    public function test_a_stale_sender_object_waiting_for_its_own_suspension_is_rejected(): void
    {
        $process = null;
        DB::beginTransaction();
        try {
            app(SuspendTutorProfile::class)->execute($this->admin, $this->tutor->tutorProfile->id, true, 'Race test suspension.');
            $process = $this->startWorker(['mode' => 'send', 'actor' => $this->tutor->id, 'conversation' => $this->chat, 'payload' => Fixtures::payload()]);
            $this->waitForBlock($process);
            DB::commit();
            $result = $this->workerResult($process);
            $this->assertSame(403, $result['status']);
            $this->assertSame('account_inactive', $result['code']);
            $this->assertDatabaseCount('messages', 0);
        } finally {
            $this->cleanup($process === null ? [] : [$process]);
        }
    }

    /** @param list<array{int,array}> $requests
     * @return list<array<string,mixed>>
     */
    private function raceSends(array $requests): array
    {
        $processes = [];
        DB::beginTransaction();
        try {
            DB::table('users')->where('id', $this->customer->id)->lockForUpdate()->first();
            foreach ($requests as [$conversation, $payload]) {
                $process = $this->startWorker(['mode' => 'send', 'actor' => $this->customer->id, 'conversation' => $conversation, 'payload' => $payload]);
                $processes[] = $process;
                $this->waitForBlock($process);
            }
            DB::commit();

            return array_map(fn (Process $process): array => $this->workerResult($process), $processes);
        } finally {
            $this->cleanup($processes);
        }
    }

    /** @param array<string,mixed> $data */
    private function startWorker(array $data): Process
    {
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
                if ($data['mode'] === 'send') {
                    $sent = app(\App\Actions\Messaging\SendTextMessage::class)->execute($actor, $data['conversation'], $data['payload'], (string) \Illuminate\Support\Str::uuid());
                    $result = ['status' => $sent['created'] ? 201 : 200, 'id' => (int) $sent['message']->id];
                } else {
                    app(\App\Actions\TutorProfiles\SuspendTutorProfile::class)->execute($actor, $data['profile'], true, 'Race test suspension.');
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

    /** @param list<int> $expected
     * @param  list<array<string,mixed>>  $results
     */
    private function assertStatuses(array $expected, array $results): void
    {
        $actual = array_column($results, 'status');
        sort($actual);
        $this->assertSame($expected, $actual);
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
