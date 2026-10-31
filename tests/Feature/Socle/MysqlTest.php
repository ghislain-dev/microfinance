<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

// Lancé par `composer test:mysql` (conteneur docker-compose.yml, base microfinance_test).
it('se connecte à un vrai MySQL 8 (pas MariaDB, pas SQLite)', function () {
    $version = (string) DB::scalar('select version()');

    expect(DB::connection()->getDriverName())->toBe('mysql')
        ->and($version)->not->toContain('MariaDB')
        ->and(version_compare($version, '8.0.0', '>='))->toBeTrue();
})->group('mysql');
