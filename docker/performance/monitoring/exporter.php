<?php

declare(strict_types=1);

if ($_SERVER['REQUEST_URI'] !== '/metrics') {
    http_response_code(404);

    return;
}

header('Content-Type: text/plain; version=0.0.4');

$dbGauges = [
    'client_connections', 'connection_limit', 'lock_waiters', 'pending_outbox',
    'oldest_outbox_seconds', 'failed_outbox', 'queued_jobs', 'failed_jobs',
];
$dbCounters = [
    'client_sessions_total' => 'client_sessions_cumulative',
    'deadlocks_total' => 'deadlocks_cumulative',
    'wal_bytes_total' => 'wal_bytes_cumulative',
];

try {
    $db = new PDO('pgsql:host=db;dbname=tutorlink_capacity', 'capacity_monitor', getenv('PERF_MONITOR_PASSWORD'));
    $state = json_decode($db->query(file_get_contents('/monitoring/resources.sql'))->fetchColumn(), true, flags: JSON_THROW_ON_ERROR);
} catch (Throwable) {
    $state = null;
}

echo "# TYPE tutorlink_db_up gauge\n";
printf("tutorlink_db_up %d\n", is_array($state));
if (is_array($state)) {
    foreach ($dbGauges as $name) {
        printf("# TYPE tutorlink_db_%s gauge\ntutorlink_db_%s %s\n", $name, $name, $state[$name]);
    }
    foreach ($dbCounters as $name => $field) {
        printf("# TYPE tutorlink_db_%s counter\ntutorlink_db_%s %s\n", $name, $name, $state[$field]);
    }
}

$poolPaths = ['app:9000' => '/fpm-status', 'app-replica:9000' => '/fpm-status/replica'];
$selected = explode(' ', getenv('PERF_MONITOR_UPSTREAMS'));
$fpmFields = [
    'listen_queue' => 'listen queue',
    'active_processes' => 'active processes',
    'idle_processes' => 'idle processes',
];
$pools = [];
$context = stream_context_create(['http' => ['timeout' => 0.6]]);
foreach ($poolPaths as $upstream => $path) {
    if (in_array($upstream, $selected, true)) {
        $raw = @file_get_contents('http://proxy:8081'.$path.'?json', context: $context);
        $pools[$upstream] = $raw === false ? null : json_decode($raw, true);
    }
}

echo "# TYPE tutorlink_fpm_up gauge\n";
foreach ($pools as $upstream => $pool) {
    printf("tutorlink_fpm_up{upstream=\"%s\"} %d\n", $upstream, is_array($pool));
}
foreach ($fpmFields as $name => $field) {
    printf("# TYPE tutorlink_fpm_%s gauge\n", $name);
    foreach ($pools as $upstream => $pool) {
        if (isset($pool[$field])) {
            printf("tutorlink_fpm_%s{upstream=\"%s\"} %d\n", $name, $upstream, $pool[$field]);
        }
    }
}
