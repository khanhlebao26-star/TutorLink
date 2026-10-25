<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class SendSlackOperationalAlert implements ShouldQueue
{
    use Queueable;

    public int $tries = 10;

    public int $timeout = 20;

    private int $queuedAt;

    private const PERMANENT_ERRORS = [
        'not_authed',
        'invalid_auth',
        'token_revoked',
        'account_inactive',
        'missing_scope',
        'no_permission',
        'access_denied',
        'channel_not_found',
        'not_in_channel',
        'is_archived',
        'restricted_action',
        'restricted_action_read_only_channel',
        'ekm_access_denied',
        'invalid_arguments',
        'no_text',
        'msg_too_long',
    ];

    public function __construct(
        public readonly string $alertId,
        public readonly string $kind,
        public readonly array $context,
    ) {
        // Validate before serialization so private/unknown fields never enter jobs/failed_jobs.
        $this->message();
        $this->queuedAt = now()->timestamp;
        $this->onConnection('database');
        $this->onQueue('slack');
    }

    public function handle(): void
    {
        if (now()->timestamp >= $this->queuedAt + 3600) {
            $this->failSafely('slack_retry_window_expired');

            return;
        }

        $token = config('services.slack.notifications.bot_user_oauth_token');
        $channel = config('services.slack.notifications.channel');

        if (
            ! is_string($token) || trim($token) === ''
            || ! is_string($channel)
            || preg_match('/^[CG][A-Z0-9]{8,}$/D', $channel) !== 1
        ) {
            $this->failSafely('slack_configuration_invalid');

            return;
        }

        try {
            $text = $this->message();
        } catch (InvalidArgumentException) {
            $this->failSafely('slack_alert_invalid');

            return;
        }

        try {
            $response = Http::asJson()
                ->acceptJson()
                ->withToken($token)
                ->connectTimeout(3)
                ->timeout(10)
                ->withoutRedirecting()
                ->post('https://slack.com/api/chat.postMessage', [
                    'channel' => $channel,
                    'text' => $text,
                    'mrkdwn' => false,
                    'unfurl_links' => false,
                    'unfurl_media' => false,
                ]);
        } catch (ConnectionException) {
            // Never retain the exception: it may contain request details or secrets.
            $this->retryOrFail('slack_connection_error');

            return;
        } catch (Throwable) {
            $this->retryOrFail('slack_client_error');

            return;
        }

        $retryAfter = filter_var(
            $response->header('Retry-After'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );
        $retryAfter = $retryAfter === false ? null : $retryAfter;

        if ($response->status() === 429) {
            $this->retryOrFail('slack_rate_limited', $retryAfter);

            return;
        }

        if ($response->serverError() || $response->status() === 408) {
            $this->retryOrFail('slack_http_temporary_error', $retryAfter);

            return;
        }

        if (! $response->successful()) {
            $this->failSafely('slack_http_'.$response->status());

            return;
        }

        $body = $response->json();

        if (! is_array($body) || ! is_bool($body['ok'] ?? null)) {
            $this->retryOrFail('slack_invalid_response');

            return;
        }

        if ($body['ok'] === true) {
            $this->record('sent', 'slack_sent');

            return;
        }

        $error = $body['error'] ?? null;

        if (in_array($error, self::PERMANENT_ERRORS, true)) {
            $this->failSafely('slack_'.$error);

            return;
        }

        if (in_array($error, ['ratelimited', 'rate_limited'], true)) {
            $this->retryOrFail('slack_rate_limited', $retryAfter);

            return;
        }

        // Map all other API errors to a fixed code; never log arbitrary response text.
        $this->retryOrFail('slack_api_temporary_or_unknown_error');
    }

    public function failed(?Throwable $exception): void
    {
        $this->record('failed', 'slack_job_failed');
    }

    private function retryOrFail(string $code, ?int $delay = null): void
    {
        $maximumDelay = min(300, 10 * (2 ** min($this->attempts() - 1, 5)));
        $delay ??= random_int((int) ceil($maximumDelay / 2), $maximumDelay);
        $remaining = $this->queuedAt + 3600 - now()->timestamp;

        if ($this->attempts() >= $this->tries || $delay >= $remaining) {
            $this->failSafely($code);

            return;
        }

        // A valid Retry-After is not shortened by the normal 300-second backoff cap.
        $this->record('retry', $code);
        $this->release($delay);
    }

    private function failSafely(string $code): void
    {
        $this->record('failed', $code);
        $this->fail(new RuntimeException($code));
    }

    private function record(string $status, string $code): void
    {
        Log::log($status === 'sent' ? 'info' : 'warning', 'Slack operational alert', [
            'alert_id' => Str::isUuid($this->alertId) ? $this->alertId : 'invalid',
            'status' => $status,
            'error_code' => $code,
            'attempt' => $this->attempts(),
            'at' => now()->toISOString(),
        ]);
    }

    private function message(): string
    {
        $required = match ($this->kind) {
            'outbox_failed' => ['failed_count', 'outbox_id', 'event_type', 'error_code', 'failed_at'],
            'outbox_pending' => ['pending_count', 'oldest_pending_seconds', 'checked_at'],
            'worker_down' => ['process', 'detected_at', 'last_heartbeat_at'],
            default => throw new InvalidArgumentException('Unsupported operational alert.'),
        };

        if (
            ! Str::isUuid($this->alertId)
            || array_diff(array_keys($this->context), $required) !== []
            || array_diff($required, array_keys($this->context)) !== []
        ) {
            throw new InvalidArgumentException('Invalid operational alert fields.');
        }

        foreach (['failed_count', 'pending_count', 'oldest_pending_seconds', 'failed_at', 'checked_at', 'detected_at', 'last_heartbeat_at'] as $field) {
            if (! array_key_exists($field, $this->context)) {
                continue;
            }

            if ($field === 'last_heartbeat_at' && $this->context[$field] === null) {
                continue;
            }

            if (! is_int($this->context[$field]) || $this->context[$field] < 0) {
                throw new InvalidArgumentException('Invalid operational alert number.');
            }
        }

        $environment = (string) config('app.env');
        $environment = preg_match('/^[a-zA-Z0-9_-]{1,32}$/D', $environment) === 1
            ? $environment
            : 'unknown';
        $prefix = "[{$environment}] {$this->kind} | alert={$this->alertId}";

        if ($this->kind === 'outbox_failed') {
            if (
                $this->context['failed_count'] < 1
                || ! is_string($this->context['outbox_id'])
                || ! Str::isUuid($this->context['outbox_id'])
                || ! is_string($this->context['event_type'])
                || preg_match('/^[a-z][a-z_]{0,99}$/D', $this->context['event_type']) !== 1
                || ! in_array($this->context['error_code'], [
                    'notification_key_conflict',
                    'notification_write_failed',
                    'retry_attempts_exhausted',
                    'retry_window_expired',
                ], true)
            ) {
                throw new InvalidArgumentException('Invalid failed outbox summary.');
            }

            return $prefix."\nfailed_count={$this->context['failed_count']}"
                ."\noutbox_id={$this->context['outbox_id']}"
                ."\nevent_type={$this->context['event_type']}"
                ."\nerror_code={$this->context['error_code']}"
                ."\nfailed_at=".gmdate('Y-m-d\TH:i:s\Z', $this->context['failed_at']);
        }

        if ($this->kind === 'outbox_pending') {
            if ($this->context['pending_count'] < 1 || $this->context['oldest_pending_seconds'] <= 300) {
                throw new InvalidArgumentException('Invalid pending outbox summary.');
            }

            return $prefix."\npending_count={$this->context['pending_count']}"
                ."\noldest_pending_seconds={$this->context['oldest_pending_seconds']}"
                ."\nchecked_at=".gmdate('Y-m-d\TH:i:s\Z', $this->context['checked_at']);
        }

        if (! in_array($this->context['process'], ['outbox-worker', 'slack-worker', 'scheduler'], true)) {
            throw new InvalidArgumentException('Invalid worker summary.');
        }

        $heartbeat = $this->context['last_heartbeat_at'] === null
            ? 'unknown'
            : gmdate('Y-m-d\TH:i:s\Z', $this->context['last_heartbeat_at']);

        return $prefix."\nprocess={$this->context['process']}"
            ."\ndetected_at=".gmdate('Y-m-d\TH:i:s\Z', $this->context['detected_at'])
            ."\nlast_heartbeat_at={$heartbeat}";
    }
}
