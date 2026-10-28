<?php

namespace Tests\Feature;

use App\Jobs\SendSlackOperationalAlert;
use App\Models\User;
use App\Support\NotificationOutboxWriter;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class SendSlackOperationalAlertTest extends TestCase
{
    use DatabaseTruncation;

    private const URL = 'https://slack.com/api/chat.postMessage';

    private const TOKEN = 'xoxb-test-secret-never-use-for-real';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.slack.notifications.bot_user_oauth_token' => self::TOKEN,
            'services.slack.notifications.channel' => 'C12345678',
        ]);
        Http::preventStrayRequests();
        Log::spy();
    }

    public function test_success_uses_backend_token_and_safe_operational_message(): void
    {
        Http::fake([self::URL => Http::response(['ok' => true], 200)]);
        $job = $this->alert()->withFakeQueueInteractions();

        $job->handle();

        $job->assertNotFailed()->assertNotReleased();
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === self::URL
                && $request->hasHeader('Authorization', 'Bearer '.self::TOKEN)
                && $request['channel'] === 'C12345678'
                && str_contains($request['text'], 'outbox_failed')
                && ! str_contains($request['text'], self::TOKEN)
                && $request['mrkdwn'] === false;
        });
    }

    public function test_pending_and_worker_alerts_have_only_operational_fields(): void
    {
        Http::fake([self::URL => Http::response(['ok' => true], 200)]);

        $pending = new SendSlackOperationalAlert((string) Str::uuid(), 'outbox_pending', [
            'pending_count' => 3,
            'oldest_pending_seconds' => 360,
            'checked_at' => now()->timestamp,
        ]);
        $worker = new SendSlackOperationalAlert((string) Str::uuid(), 'worker_down', [
            'process' => 'outbox-worker',
            'detected_at' => now()->timestamp,
            'last_heartbeat_at' => null,
        ]);

        $pending->withFakeQueueInteractions()->handle();
        $worker->withFakeQueueInteractions()->handle();

        $pending->assertNotFailed();
        $worker->assertNotFailed();
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => str_contains($request['text'], 'pending_count=3'));
        Http::assertSent(fn (Request $request): bool => str_contains($request['text'], 'last_heartbeat_at=unknown'));
    }

    public function test_connection_timeout_releases_job_without_inline_retry(): void
    {
        Http::fake([self::URL => Http::failedConnection('Timed out: '.self::TOKEN)]);
        $job = $this->alert()->withFakeQueueInteractions();

        $job->handle();

        $job->assertReleased()->assertNotFailed();
        $this->assertGreaterThanOrEqual(5, $job->job->releaseDelay);
        $this->assertLessThanOrEqual(10, $job->job->releaseDelay);
    }

    public function test_rate_limit_respects_retry_after(): void
    {
        Http::fake([self::URL => Http::response(['ok' => false], 429, ['Retry-After' => '45'])]);
        $job = $this->alert()->withFakeQueueInteractions();

        $job->handle();

        $job->assertReleased(45)->assertNotFailed();
        Http::assertSentCount(1);
    }

    public function test_retry_after_is_not_shortened_to_normal_backoff_cap(): void
    {
        Http::fake([self::URL => Http::response([], 429, ['Retry-After' => '600'])]);
        $job = $this->alert()->withFakeQueueInteractions();

        $job->handle();

        $job->assertReleased(600)->assertNotFailed();
    }

    public function test_invalid_retry_after_uses_bounded_backoff(): void
    {
        Http::fake([self::URL => Http::response([], 429, ['Retry-After' => 'invalid'])]);
        $job = $this->alert()->withFakeQueueInteractions();

        $job->handle();

        $job->assertReleased()->assertNotFailed();
        $this->assertGreaterThanOrEqual(5, $job->job->releaseDelay);
        $this->assertLessThanOrEqual(10, $job->job->releaseDelay);
    }

    public function test_retry_after_beyond_retry_window_fails_without_early_retry(): void
    {
        Http::fake([self::URL => Http::response([], 429, ['Retry-After' => '7200'])]);
        $job = $this->alert()->withFakeQueueInteractions();

        $job->handle();

        $job->assertFailedWith(new RuntimeException('slack_rate_limited'))
            ->assertNotReleased();
    }

    #[DataProvider('temporaryHttpStatuses')]
    public function test_temporary_http_error_releases_job(int $status): void
    {
        Http::fake([self::URL => Http::response('Private upstream body', $status)]);
        $job = $this->alert()->withFakeQueueInteractions();

        $job->handle();

        $job->assertReleased()->assertNotFailed();
        Http::assertSentCount(1);
    }

    public static function temporaryHttpStatuses(): array
    {
        return [[408], [500], [503]];
    }

    public function test_http_200_with_temporary_api_error_is_retried(): void
    {
        Http::fake([self::URL => Http::response(['ok' => false, 'error' => 'internal_error'], 200)]);
        $job = $this->alert()->withFakeQueueInteractions();

        $job->handle();

        $job->assertReleased()->assertNotFailed();
    }

    public function test_http_200_with_api_rate_limit_respects_retry_after(): void
    {
        Http::fake([
            self::URL => Http::response(
                ['ok' => false, 'error' => 'ratelimited'],
                200,
                ['Retry-After' => '30'],
            ),
        ]);
        $job = $this->alert()->withFakeQueueInteractions();

        $job->handle();

        $job->assertReleased(30)->assertNotFailed();
    }

    #[DataProvider('permanentApiErrors')]
    public function test_permanent_api_error_fails_without_retry(string $error): void
    {
        Http::fake([self::URL => Http::response(['ok' => false, 'error' => $error], 200)]);
        $job = $this->alert()->withFakeQueueInteractions();

        $job->handle();

        $job->assertFailedWith(new RuntimeException('slack_'.$error))
            ->assertNotReleased();
    }

    public static function permanentApiErrors(): array
    {
        return [
            ['invalid_auth'],
            ['token_revoked'],
            ['missing_scope'],
            ['channel_not_found'],
            ['not_in_channel'],
            ['is_archived'],
        ];
    }

    public function test_http_auth_error_fails_without_retry(): void
    {
        Http::fake([self::URL => Http::response('Private auth response', 403)]);
        $job = $this->alert()->withFakeQueueInteractions();

        $job->handle();

        $job->assertFailedWith(new RuntimeException('slack_http_403'))
            ->assertNotReleased();
    }

    public function test_invalid_json_response_is_not_treated_as_success(): void
    {
        Http::fake([self::URL => Http::response('not-json', 200)]);
        $job = $this->alert()->withFakeQueueInteractions();

        $job->handle();

        $job->assertReleased()->assertNotFailed();
    }

    public function test_missing_configuration_fails_without_http_request(): void
    {
        config(['services.slack.notifications.bot_user_oauth_token' => '']);
        $job = $this->alert()->withFakeQueueInteractions();

        $job->handle();

        $job->assertFailedWith(new RuntimeException('slack_configuration_invalid'));
        Http::assertNothingSent();
    }

    public function test_tenth_temporary_failure_stops_retry_with_safe_exception(): void
    {
        Http::fake([self::URL => Http::failedConnection('Secret connection error: '.self::TOKEN)]);
        $job = $this->alert()->withFakeQueueInteractions();
        $job->job->attempts = 10;

        $job->handle();

        $job->assertFailedWith(new RuntimeException('slack_connection_error'))
            ->assertNotReleased();
        $this->assertNull($job->job->failedWith->getPrevious());
        $this->assertStringNotContainsString(self::TOKEN, (string) $job->job->failedWith);
    }

    public function test_expired_job_does_not_send_to_slack(): void
    {
        $job = $this->alert()->withFakeQueueInteractions();
        $this->travel(61)->minutes();

        $job->handle();

        $job->assertFailedWith(new RuntimeException('slack_retry_window_expired'));
        Http::assertNothingSent();
    }

    public function test_unknown_response_content_and_token_are_not_logged_or_serialized(): void
    {
        $private = 'PRIVATE_EVIDENCE_DO_NOT_LOG';
        Http::fake([
            self::URL => Http::response([
                'ok' => false,
                'error' => $private.self::TOKEN,
            ], 200),
        ]);
        $job = $this->alert()->withFakeQueueInteractions();

        $job->handle();

        $job->assertReleased();
        Log::shouldHaveReceived('log')->once()->withArgs(function ($level, $message, $context) use ($private): bool {
            $serialized = json_encode([$message, $context], JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString(self::TOKEN, $serialized);
            $this->assertStringNotContainsString($private, $serialized);
            $this->assertSame('slack_api_temporary_or_unknown_error', $context['error_code']);

            return true;
        });
        $this->assertStringNotContainsString(self::TOKEN, serialize($job));
        $this->assertStringNotContainsString($private, serialize($job));
    }

    public function test_private_fields_are_rejected_before_queue_serialization(): void
    {
        $context = $this->context();
        $context['payload'] = ['body' => 'Private business content'];

        $this->expectException(InvalidArgumentException::class);

        new SendSlackOperationalAlert((string) Str::uuid(), 'outbox_failed', $context);
    }

    public function test_slack_failure_does_not_rollback_committed_business_or_notification(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Worker integration requires PostgreSQL.');
        }

        Http::fake([self::URL => Http::response([], 503)]);
        $recipient = User::factory()->create();

        DB::transaction(function () use ($recipient): void {
            $recipient->forceFill(['full_name' => 'Committed business change'])->save();
            app(NotificationOutboxWriter::class)->record(
                'tutor_profile_approved',
                'tutor_profile',
                17,
                'profile:17:approve:slack-test',
                [$recipient->id],
                ['deep_link' => '/profile'],
            );
            Bus::dispatch($this->alert());
        });

        Http::assertNothingSent();
        $this->assertDatabaseHas('jobs', ['queue' => 'slack']);
        $this->artisan('outbox:process', ['--limit' => 10])->assertExitCode(0)->run();

        $this->runSlackWorker();

        $this->assertSame('Committed business change', $recipient->fresh()->full_name);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertNotNull(DB::table('notification_outbox')->value('processed_at'));
        $this->assertDatabaseHas('jobs', ['queue' => 'slack', 'attempts' => 1]);
        $this->assertDatabaseCount('failed_jobs', 0);
        Http::assertSentCount(1);
    }

    public function test_permanent_failure_is_stored_in_failed_jobs_without_token(): void
    {
        Http::fake([self::URL => Http::response(['ok' => false, 'error' => 'invalid_auth'], 200)]);
        Bus::dispatch($this->alert());

        $this->runSlackWorker();

        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 1);
        $failed = DB::table('failed_jobs')->first();
        $this->assertStringContainsString('slack_invalid_auth', $failed->exception);
        $this->assertStringNotContainsString(self::TOKEN, $failed->exception);
        $this->assertStringNotContainsString(self::TOKEN, $failed->payload);
    }

    public function test_rolled_back_enqueue_does_not_send_any_slack_request(): void
    {
        Http::fake([self::URL => Http::response(['ok' => true], 200)]);

        try {
            DB::transaction(function (): void {
                Bus::dispatch($this->alert());

                throw new RuntimeException('rollback_test');
            });

            $this->fail('Expected rollback.');
        } catch (RuntimeException $exception) {
            $this->assertSame('rollback_test', $exception->getMessage());
        }

        $this->assertDatabaseCount('jobs', 0);
        $this->runSlackWorker();
        Http::assertNothingSent();
    }

    private function runSlackWorker(): void
    {
        $this->artisan('queue:work', [
            'connection' => 'database',
            '--queue' => 'slack',
            '--once' => true,
            '--sleep' => 0,
        ])->assertExitCode(0)->run();
    }

    private function alert(): SendSlackOperationalAlert
    {
        return new SendSlackOperationalAlert((string) Str::uuid(), 'outbox_failed', $this->context());
    }

    private function context(): array
    {
        return [
            'failed_count' => 1,
            'outbox_id' => '123e4567-e89b-42d3-a456-426614174000',
            'event_type' => 'tutor_profile_approved',
            'error_code' => 'notification_write_failed',
            'failed_at' => now()->timestamp,
        ];
    }
}
