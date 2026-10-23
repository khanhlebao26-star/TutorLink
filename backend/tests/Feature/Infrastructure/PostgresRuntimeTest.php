<?php

use Illuminate\Support\Facades\DB;

it('boots the guarded Laravel test application on PostgreSQL', function () {
    expect(app()->environment())->toBe('testing');

    expect(DB::connection()->getDriverName())->toBe('pgsql');

    $database = DB::selectOne('SELECT current_database() AS name');

    expect($database->name)->toBe('tutorlink_test');
});
