<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('boots against the isolated MySQL test database', function () {
    expect(config('database.default'))->toBe('mysql')
        ->and(DB::connection()->getDriverName())->toBe('mysql')
        ->and(Schema::hasTable('users'))->toBeTrue();

    $this->get('/')->assertSuccessful();
});
