<?php

// Read-only verification; run from the backend application directory.
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$row = Illuminate\Support\Facades\DB::table('notification_outbox')
    ->where('id', '67e77d46-3367-4f3f-baeb-2530d6153fec')
    ->first(['id', 'user_id', 'type', 'event_key', 'created_at', 'processed_at', 'failed_at']);

echo json_encode([
    'observed_at' => now()->toIso8601String(),
    'source' => 'Fresh read-only verification after user-operated Slack demo',
    'outbox' => $row,
    'notification_count' => $row === null ? null : Illuminate\Support\Facades\DB::table('notifications')
        ->where('user_id', $row->user_id)
        ->where('type', $row->type)
        ->where('event_key', $row->event_key)
        ->count(),
    'token_configured' => filled(config('services.slack.notifications.bot_user_oauth_token')),
    'channel' => config('services.slack.notifications.channel'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
