<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Keep the test suite isolated from Docker's runtime environment.
     *
     * PHPUnit's env entries do not replace variables already exported by
     * Docker Compose, while Laravel reads both $_ENV and $_SERVER during
     * bootstrap. Set all three sources before the application is created so
     * RefreshDatabase can never target the local PostgreSQL database.
     */
    public function createApplication()
    {
        $environment = [
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'CACHE_STORE' => 'array',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
            'MAIL_MAILER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
        ];

        foreach ($environment as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        return parent::createApplication();
    }
}
