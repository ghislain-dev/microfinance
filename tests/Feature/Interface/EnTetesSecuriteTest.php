<?php

declare(strict_types=1);

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

it('envoie les en-têtes de sécurité sur le web et l’API', function (string $url) {
    $reponse = str_starts_with($url, '/api') ? getJson($url) : get($url);

    $reponse->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'same-origin');

    $csp = (string) $reponse->headers->get('Content-Security-Policy');
    expect($csp)->toContain("default-src 'self'", "frame-ancestors 'none'", "object-src 'none'")
        ->and(str_contains($csp, 'unsafe-eval'))->toBeFalse()
        ->and(str_contains($csp, 'unsafe-inline'))->toBeFalse();
})->with(['/connexion', '/api/v1/moi']);

it('n’envoie HSTS qu’en production (HTTPS)', function () {
    get('/connexion')->assertHeaderMissing('Strict-Transport-Security');

    app()['env'] = 'production';

    expect(get('/connexion')->headers->get('Strict-Transport-Security'))->toContain('max-age=31536000');
});
