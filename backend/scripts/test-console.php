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
    'd04:seed',
    'd04:migrate',
    'openapi:export',
];

if (
    count($argv) > 2
    || ! in_array($command, $allowedCommands, true)
) {
    fwrite(
        STDERR,
        'Usage: php scripts/test-console.php '
        ."[check|migrate|migrate:fresh|db:seed|d04:seed|d04:migrate|openapi:export]\n",
    );

    exit(2);
}

try {
    $d04FixtureSeed = $command === 'd04:seed';
    $d04SchemaMigration = $command === 'd04:migrate';
    if ($d04FixtureSeed) {
        $command = 'db:seed';
    } elseif ($d04SchemaMigration) {
        $command = 'migrate';
    }

    $app = require dirname(__DIR__).'/bootstrap/app.php';

    $app->afterBootstrapping(
        LoadConfiguration::class,
        function ($app): void {
            TestDatabaseGuard::assertSafe($app['config']);
        },
    );

    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();
    $identity = TestDatabaseGuard::assertConnectedIdentity($app['config']);

    if ($command === 'check') {
        fwrite(
            STDOUT,
            'SAFE: test database identity verified. '
            .'driver='.$identity['driver']
            .' config_cache='.($app->configurationIsCached() ? 'present' : 'absent')
            .' configured_host='.($identity['configured_host'] ?? 'n/a')
            .' database='.$identity['database']
            .' username='.($identity['username'] ?? 'n/a')
            .' server_address='.($identity['server_address'] ?? 'n/a')
            .PHP_EOL,
        );

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
        if ($d04FixtureSeed && $artisanCommand === 'db:seed') {
            $options['--class'] = 'Database\\Seeders\\BeD04FixtureSeeder';
        }
        if ($d04SchemaMigration && $artisanCommand === 'migrate') {
            $options['--path'] = 'database/migrations/2026_11_01_233000_add_be_d04_workspace_schema.php';
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
