<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PostgresConnections
{
    public static function run(callable $callback): mixed
    {
        TestDatabaseGuard::assertSafe(config());

        $default = config('database.default');
        $configuration = config("database.connections.{$default}");

        if (($configuration['driver'] ?? null) !== 'pgsql') {
            throw new RuntimeException(
                'Independent connection tests require PostgreSQL.',
            );
        }

        $configuration['options'] = array_replace(
            $configuration['options'] ?? [],
            [\PDO::ATTR_PERSISTENT => false],
        );

        $suffix = bin2hex(random_bytes(6));
        $nameA = "test_pg_a_{$suffix}";
        $nameB = "test_pg_b_{$suffix}";

        try {
            $connectionA = DB::connectUsing($nameA, $configuration);
            $connectionB = DB::connectUsing($nameB, $configuration);

            return $callback($connectionA, $connectionB);
        } finally {
            DB::purge($nameA);
            DB::purge($nameB);
        }
    }
}
