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
    'openapi:export',
];

if (
    count($argv) > 2
    || ! in_array($command, $allowedCommands, true)
) {
    fwrite(
        STDERR,
        'Usage: php scripts/test-console.php '
        ."[check|migrate|migrate:fresh|db:seed|openapi:export]\n",
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

    if (
        $command === 'openapi:export'
        && (
            $app['config']->get('database.default') !== 'sqlite'
            || $app['config']->get(
                'database.connections.sqlite.database',
            ) !== ':memory:'
        )
    ) {
        throw new RuntimeException(
            'OpenAPI export requires SQLite :memory:.',
        );
    }

    $commands = $command === 'openapi:export'
        ? ['migrate', 'scramble:export']
        : [$command];

    foreach ($commands as $artisanCommand) {
        $options = ['--no-interaction' => true];

        if ($artisanCommand !== 'scramble:export') {
            $options['--force'] = true;
        }

        $exitCode = $kernel->call($artisanCommand, $options);

        fwrite(STDOUT, $kernel->output());

        if ($exitCode !== 0) {
            exit($exitCode);
        }
    }

    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage().PHP_EOL);

    exit(1);
}
