<?php

namespace Tests\Support;

use Illuminate\Contracts\Config\Repository;
use RuntimeException;

final class TestDatabaseGuard
{
    public static function assertSafe(Repository $config): void
    {
        $connection = $config->get('database.default');
        $database = $config->get(
            "database.connections.{$connection}",
            [],
        );

        $safeTarget = (
            $connection === 'sqlite'
            && ($database['driver'] ?? null) === 'sqlite'
            && ($database['database'] ?? null) === ':memory:'
        ) || (
            $connection === 'pgsql'
            && ($database['driver'] ?? null) === 'pgsql'
            && in_array($database['database'] ?? null, [
                'tutorlink_test',
                'tutorlink_phase2_test',
            ], true)
        );

        if (
            $config->get('app.env') !== 'testing'
            || ! $safeTarget
            || ! empty($database['url'])
            || ! empty($database['read'])
            || ! empty($database['write'])
        ) {
            throw new RuntimeException(
                'Unsafe test database configuration. '
                .'Use SQLite :memory: or an explicitly isolated '
                .'tutorlink_test/tutorlink_phase2_test database.',
            );
        }
    }
}
