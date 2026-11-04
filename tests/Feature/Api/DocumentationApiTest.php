<?php

declare(strict_types=1);

use Illuminate\Routing\Route;

it('documente chaque route de l’API v1 dans docs/api/openapi.json', function () {
    $doc = json_decode((string) file_get_contents(base_path('docs/api/openapi.json')), true, flags: JSON_THROW_ON_ERROR);
    expect($doc)->toBeArray()->and($doc['openapi'] ?? null)->toStartWith('3.');

    $manquantes = [];
    foreach (app('router')->getRoutes()->getRoutes() as $route) {
        /** @var Route $route */
        if (! str_starts_with($route->uri(), 'api/v1/')) {
            continue;
        }
        $chemin = '/'.substr($route->uri(), strlen('api/v1/'));
        foreach (array_diff($route->methods(), ['HEAD']) as $methode) {
            if (! isset($doc['paths'][$chemin][strtolower($methode)])) {
                $manquantes[] = "{$methode} {$chemin}";
            }
        }
    }

    expect($manquantes)->toBe([]);
});
