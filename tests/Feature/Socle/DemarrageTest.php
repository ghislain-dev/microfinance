<?php

declare(strict_types=1);

use function Pest\Laravel\get;

it('démarre l’application', function () {
    get('/up')->assertOk();
});

it('tourne sur Laravel 13 et PHP 8.3 minimum', function () {
    expect(app()->version())->toStartWith('13.')
        ->and(version_compare(PHP_VERSION, '8.3.0', '>='))->toBeTrue();
});
