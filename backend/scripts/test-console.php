<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Tests\Support\TestDatabaseGuard;

require dirname(__DIR__).'/vendor/autoload.php';

$command = $argv[1] ?? 'check';

$allowedCommands = [
    'check',
    'migrate',
    'migrate:fresh',
    'db:seed',
];

if (
    count($argv) > 2
    || ! in_array($command, $allowedCommands, true)
) {
    fwrite(
        STDERR,
        'Usage: php scripts/test-console.php '
        ."[check|migrate|migrate:fresh|db:seed]\n",
    );

    exit(2);
}

try {
    $app = require dirname(__DIR__).'/bootstrap/app.php';

    $app->afterBootstrapping(
        LoadConfiguration::class,
        function ($app): void {
            TestDatabaseGuard::assertSafe($app['config']);
        },
    );

    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();

    if ($command === 'check') {
        fwrite(STDOUT, "SAFE: test database configuration accepted.\n");

        exit(0);
    }

    $exitCode = $kernel->call($command, [
        '--force' => true,
        '--no-interaction' => true,
    ]);

    fwrite(STDOUT, $kernel->output());

    exit($exitCode);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage().PHP_EOL);

    exit(1);
}
