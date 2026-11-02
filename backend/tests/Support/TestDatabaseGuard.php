<?php

namespace Tests\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\DB;
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
            && in_array($database['host'] ?? null, [
                'test-db',
                '127.0.0.1',
                'localhost',
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

    public static function assertConnectedIdentity(Repository $config): array
    {
        self::assertSafe($config);

        $connection = $config->get('database.default');
        $database = $config->get("database.connections.{$connection}", []);

        if (($database['driver'] ?? null) === 'sqlite') {
            return [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'configured_host' => null,
                'server_address' => null,
            ];
        }

        $identity = (array) DB::connection($connection)->selectOne(
            'select current_database() as database, current_user as username, host(inet_server_addr()) as server_address',
        );
        $serverAddress = $identity['server_address'] ?? null;
        $isPrivateAddress = is_string($serverAddress)
            && filter_var($serverAddress, FILTER_VALIDATE_IP) !== false
            && filter_var(
                $serverAddress,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            ) === false;

        if (
            ($identity['database'] ?? null) !== ($database['database'] ?? null)
            || ($identity['username'] ?? null) !== ($database['username'] ?? null)
            || ! $isPrivateAddress
        ) {
            throw new RuntimeException(
                'Unsafe test database connection identity.',
            );
        }

        return [
            'driver' => 'pgsql',
            'database' => $identity['database'],
            'username' => $identity['username'],
            'configured_host' => $database['host'],
            'server_address' => $serverAddress,
        ];
    }
}
