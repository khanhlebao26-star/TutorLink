<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Use an isolated SQLite database by default, while allowing CI and
     * Docker to run the suite against an explicitly configured PostgreSQL.
     */
    public function createApplication()
    {
        $databaseConnection = getenv('DB_CONNECTION') ?: 'sqlite';
        $environment = [
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'FRONTEND_URL' => 'http://localhost:3000',
            'CACHE_STORE' => 'array',
            'DB_CONNECTION' => $databaseConnection,
            'APP_CONFIG_CACHE' => getenv('TEST_CONFIG_CACHE') ?: sys_get_temp_dir() . '/tutorlink-test-config.php',
            'MAIL_MAILER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'database',
        ];

        if ($databaseConnection === 'sqlite') {
            $environment['DB_DATABASE'] = ':memory:';
            $environment['DB_URL'] = '';
        }

        foreach ($environment as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        $app = require dirname(__DIR__) . '/bootstrap/app.php';
        $this->traitsUsedByTest = class_uses_recursive(static::class);

        $app->afterBootstrapping(LoadConfiguration::class, function ($app): void {
            $connection = $app['config']->get('database.default');
            $database = $app['config']->get("database.connections.{$connection}", []);
            $safeTarget = ($connection === 'sqlite' && ($database['database'] ?? null) === ':memory:')
                || ($connection === 'pgsql' && in_array($database['database'] ?? null, [
                    'tutorlink_test',
                    'tutorlink_phase2_test',
                ], true));

            if (
                $app['config']->get('app.env') !== 'testing' || !$safeTarget
                || !empty($database['url']) || !empty($database['read']) || !empty($database['write'])
            ) {
                throw new RuntimeException('Unsafe test database configuration. Use SQLite :memory: or an explicitly isolated tutorlink_test/tutorlink_phase2_test database.');
            }
        });
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}