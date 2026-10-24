<?php

use Illuminate\Database\Connection;
use Tests\Support\PostgresConnections;

it('opens two distinct PostgreSQL sessions on the test database', function () {
    PostgresConnections::run(function (
        Connection $connectionA,
        Connection $connectionB,
    ): void {
        $sessionA = $connectionA->selectOne(
            'SELECT pg_backend_pid() AS pid, current_database() AS database',
        );

        $sessionB = $connectionB->selectOne(
            'SELECT pg_backend_pid() AS pid, current_database() AS database',
        );

        expect($connectionA->getPdo())
            ->not->toBe($connectionB->getPdo());

        expect((int) $sessionA->pid)
            ->not->toBe((int) $sessionB->pid);

        expect($sessionA->database)
            ->toBe(config('database.connections.pgsql.database'));

        expect($sessionB->database)
            ->toBe($sessionA->database);
    });
});

it('enforces transaction locks across independent PostgreSQL sessions', function () {
    PostgresConnections::run(function (
        Connection $connectionA,
        Connection $connectionB,
    ): void {
        $key = random_int(1, 2147483647);

        $sql = <<<'SQL'
            SELECT CASE
                WHEN pg_try_advisory_xact_lock(?, ?) THEN 1
                ELSE 0
            END AS acquired
            SQL;

        $connectionA->beginTransaction();
        $connectionB->beginTransaction();

        $lockA = $connectionA->selectOne($sql, [3701, $key]);

        expect((int) $lockA->acquired)->toBe(1);

        $lockB = $connectionB->selectOne($sql, [3701, $key]);

        expect((int) $lockB->acquired)->toBe(0);

        $connectionA->rollBack();

        $retryB = $connectionB->selectOne($sql, [3701, $key]);

        expect((int) $retryB->acquired)->toBe(1);

        $connectionB->rollBack();
    });
});
