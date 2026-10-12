<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

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
            'CACHE_STORE' => 'array',
            'DB_CONNECTION' => $databaseConnection,
            'MAIL_MAILER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
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

        return parent::createApplication();
    }
}
