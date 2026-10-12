<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class TestDatabaseIsolationTest extends TestCase
{
    public function test_development_database_is_rejected_before_providers_or_refresh_database(): void
    {
        $process = $this->probe(['DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => 'tutorlink', 'DB_HOST' => 'never-connect.invalid']);
        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('Unsafe test database configuration.', $process->getErrorOutput());
        $this->assertStringNotContainsString('SQLSTATE', $process->getErrorOutput());
    }

    public function test_sqlite_memory_is_safe_even_when_development_environment_is_inherited(): void
    {
        $process = $this->probe(['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => 'tutorlink', 'DB_URL' => 'postgres://never-connect.invalid/tutorlink']);
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertSame('SAFE', $process->getOutput());
    }

    public function test_cached_configuration_with_wrong_target_is_rejected_before_boot(): void
    {
        $cache = tempnam(sys_get_temp_dir(), 'tutorlink-guard-');
        file_put_contents($cache, '<?php return '.var_export([
            'app' => ['env' => 'testing'],
            'database' => ['default' => 'pgsql', 'connections' => ['pgsql' => ['database' => 'tutorlink', 'host' => 'never-connect.invalid']]],
        ], true).';');
        try {
            $process = $this->probe(['DB_CONNECTION' => 'sqlite', 'TEST_CONFIG_CACHE' => $cache]);
            $this->assertSame(1, $process->getExitCode());
            $this->assertStringContainsString('Unsafe test database configuration.', $process->getErrorOutput());
            $this->assertStringNotContainsString('SQLSTATE', $process->getErrorOutput());
        } finally {
            unlink($cache);
        }
    }

    private function probe(array $environment): Process
    {
        $script = <<<'PHP'
        require 'vendor/autoload.php';
        $test = new class('guard') extends \Tests\TestCase {};
        try { $test->createApplication(); echo 'SAFE'; }
        catch (\Throwable $error) { fwrite(STDERR, $error->getMessage()); exit(1); }
        PHP;
        $process = new Process([PHP_BINARY, '-r', $script], dirname(__DIR__, 2), $environment + ['TEST_CONFIG_CACHE' => '/tmp/tutorlink-no-config-cache.php']);
        $process->run();

        return $process;
    }
}
