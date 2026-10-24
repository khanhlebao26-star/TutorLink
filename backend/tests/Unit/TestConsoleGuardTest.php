<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class TestConsoleGuardTest extends TestCase
{
    public function test_unsafe_targets_are_rejected_before_connection(): void
    {
        foreach (['tutorlink', 'tutorlink_demo', 'tutorlink_tests'] as $database) {
            foreach (['migrate', 'migrate:fresh', 'db:seed'] as $command) {
                $process = $this->runConsole([$command], [
                    'DB_CONNECTION' => 'pgsql',
                    'DB_DATABASE' => $database,
                    'DB_HOST' => 'never-connect.invalid',
                ]);

                $message = "{$command} targeting {$database}";

                $this->assertSame(
                    1,
                    $process->getExitCode(),
                    $message,
                );

                $this->assertStringContainsString(
                    'Unsafe test database configuration.',
                    $process->getErrorOutput(),
                    $message,
                );

                $this->assertStringNotContainsString(
                    'SQLSTATE',
                    $process->getErrorOutput(),
                    $message,
                );
            }
        }
    }

    public function test_database_override_options_are_rejected(): void
    {
        $process = $this->runConsole([
            'migrate:fresh',
            '--database=pgsql',
        ]);

        $this->assertSame(2, $process->getExitCode());
        $this->assertStringContainsString(
            'Usage:',
            $process->getErrorOutput(),
        );
    }

    public function test_check_accepts_sqlite_memory_without_migrating(): void
    {
        $process = $this->runConsole(['check'], [
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
        ]);

        $this->assertSame(
            0,
            $process->getExitCode(),
            $process->getErrorOutput(),
        );

        $this->assertStringContainsString(
            'SAFE:',
            $process->getOutput(),
        );
    }

    private function runConsole(
        array $arguments,
        array $environment = [],
    ): Process {
        $process = new Process(
            [
                PHP_BINARY,
                'scripts/test-console.php',
                ...$arguments,
            ],
            dirname(__DIR__, 2),
            array_replace([
                'APP_ENV' => 'testing',
                'APP_CONFIG_CACHE' => '/tmp/tutorlink-console-no-cache.php',
                'DB_URL' => '',
            ], $environment),
        );

        $process->setTimeout(30);
        $process->run();

        return $process;
    }
}
