<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestDatabaseGuard;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->afterBootstrapping(LoadConfiguration::class, function ($app): void {
    TestDatabaseGuard::assertSafe($app['config']);
});
$app->make(Kernel::class)->bootstrap();
$identity = TestDatabaseGuard::assertConnectedIdentity($app['config']);

if ($identity['database'] !== 'tutorlink_test') {
    throw new RuntimeException('D06 fixture IDs can only be emitted for tutorlink_test.');
}

$id = static function (string $table, array $where): int {
    $value = DB::table($table)->where($where)->value('id');

    if ($value === null) {
        throw new RuntimeException("Required D04 fixture is missing from {$table}.");
    }

    return (int) $value;
};

$actorAliases = [
    'owner' => 'owner',
    'other_tutor' => 'other-tutor',
    'member' => 'member',
    'outsider' => 'outsider',
    'admin' => 'admin',
    'inactive' => 'inactive',
    'revoked_member' => 'revoked',
    'late_member' => 'late-member',
];
$actors = [];
foreach ($actorAliases as $alias => $handle) {
    $user = DB::table('users')->where('email', "be-d04-{$handle}@tutorlink.test")->first(['id', 'role', 'status']);

    if ($user === null) {
        throw new RuntimeException("Required D04 actor {$alias} is missing.");
    }

    $actors[$alias] = ['id' => (int) $user->id, 'role' => $user->role, 'status' => $user->status];
}

$programAliases = ['active', 'paused', 'completed', 'cancelled'];
$programs = [];
foreach ($programAliases as $alias) {
    $programs[$alias] = $id('programs', ['title' => 'D04 Fixture '.ucfirst($alias)]);
}

$activeProgramId = $programs['active'];
$aliases = [
    'actors' => $actors,
    'programs' => $programs,
    'announcements' => [
        'published_tie_a' => $id('announcements', ['program_id' => $activeProgramId, 'title' => 'D04 Fixture Published Tie A']),
        'published_tie_b' => $id('announcements', ['program_id' => $activeProgramId, 'title' => 'D04 Fixture Published Tie B']),
        'draft_owner_only' => $id('announcements', ['program_id' => $activeProgramId, 'title' => 'D04 Fixture Draft Owner Only']),
        'published_before_revocation' => $id('announcements', ['title' => 'D04 Fixture Published Before Revocation']),
    ],
    'tasks' => [],
    'fees' => [
        'zero_amount_declared' => $id('fee_records', ['program_id' => $activeProgramId, 'period_start' => '2022-01-01']),
        'current_period_declared' => $id('fee_records', ['program_id' => $activeProgramId, 'period_start' => '2026-11-01']),
    ],
    'progress' => [
        'latest_tie_a' => $id('progress_records', ['program_id' => $activeProgramId, 'note' => 'D04_FIXTURE_PROGRESS_001']),
        'latest_tie_b' => $id('progress_records', ['program_id' => $activeProgramId, 'note' => 'D04_FIXTURE_PROGRESS_002']),
        'count_sample_last' => $id('progress_records', ['program_id' => $activeProgramId, 'note' => 'D04_FIXTURE_PROGRESS_055']),
    ],
    'files' => [],
    'sessions' => [],
];

foreach (['todo', 'in_progress', 'submitted', 'completed', 'cancelled'] as $status) {
    $aliases['tasks'][$status] = $id('tasks', ['program_id' => $activeProgramId, 'title' => 'D04 Fixture Task '.ucfirst($status)]);
}

foreach (['valid', 'wrong_owner', 'wrong_purpose', 'pending', 'rejected'] as $fileAlias) {
    $aliases['files'][$fileAlias] = $id('files', ['object_key' => "be-d04/fixtures/{$fileAlias}.pdf"]);
}

foreach (['Upcoming Session', 'Completed Session', 'Cancelled Session'] as $sessionTitle) {
    $aliases['sessions'][strtolower(str_replace(' ', '_', $sessionTitle))] = $id('program_sessions', [
        'program_id' => $activeProgramId,
        'title' => 'D04 Fixture '.$sessionTitle,
    ]);
}

$aliases['submissions'] = [
    'current_pending' => (int) DB::table('tasks')->where('id', $aliases['tasks']['submitted'])->value('current_submission_id'),
    'current_accepted' => (int) DB::table('tasks')->where('id', $aliases['tasks']['completed'])->value('current_submission_id'),
];

echo json_encode([
    'fixture_set' => 'BE-D04 synthetic fixtures',
    'database' => $identity['database'],
    'generated_at' => now()->toIso8601String(),
    'aliases' => $aliases,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
