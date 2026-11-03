<?php

namespace Tests\Support;

use Laravel\Pulse\Facades\Pulse;
use Laravel\Telescope\Telescope;
use Tests\TestCase;

abstract class MonitoringTestCase extends TestCase
{
    public function createApplication()
    {
        Telescope::$filterUsing = [];
        Telescope::$filterBatchUsing = [];
        Telescope::flushEntries();
        Telescope::stopRecording();

        $previous = [];
        foreach (['TELESCOPE_ENABLED', 'PULSE_ENABLED'] as $key) {
            $previous[$key] = [getenv($key), $_ENV[$key] ?? null, $_SERVER[$key] ?? null];
            putenv($key.'=true');
            $_ENV[$key] = $_SERVER[$key] = 'true';
        }

        try {
            return parent::createApplication();
        } finally {
            foreach ($previous as $key => [$environment, $env, $server]) {
                putenv($environment === false ? $key : $key.'='.$environment);
                if ($env === null) {
                    unset($_ENV[$key]);
                } else {
                    $_ENV[$key] = $env;
                }
                if ($server === null) {
                    unset($_SERVER[$key]);
                } else {
                    $_SERVER[$key] = $server;
                }
            }
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        Telescope::flushEntries();
        Telescope::startRecording(false);
        Pulse::flush();
        config(['monitoring.request_sample_rate' => 1]);
    }

    protected function tearDown(): void
    {
        Telescope::stopRecording();
        Telescope::flushEntries();
        Pulse::flush();
        parent::tearDown();
    }
}
