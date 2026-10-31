<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Lit .env.example sous forme clé => valeur.
 *
 * @return array<string, string>
 */
function envExemple(): array
{
    $valeurs = [];
    foreach (file(base_path('.env.example'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $ligne) {
        if (str_starts_with(trim($ligne), '#') || ! str_contains($ligne, '=')) {
            continue;
        }
        [$cle, $valeur] = explode('=', $ligne, 2);
        $valeurs[trim($cle)] = trim($valeur);
    }

    return $valeurs;
}

it('lance les tests sur SQLite en mémoire', function () {
    expect(DB::connection()->getDriverName())->toBe('sqlite')
        ->and(config('database.connections.sqlite.database'))->toBe(':memory:');
});

it('protège le cookie de session (http_only, same_site=strict)', function () {
    expect(config('session.http_only'))->toBeTrue()
        ->and(config('session.same_site'))->toBe('strict');
});

it('active le mode strict d’Eloquent hors production (pas de N+1, pas d’attribut inconnu)', function () {
    expect(Model::preventsLazyLoading())->toBeTrue()
        ->and(Model::preventsSilentlyDiscardingAttributes())->toBeTrue()
        ->and(Model::preventsAccessingMissingAttributes())->toBeTrue();
});

it('fournit un .env.example sûr pour MySQL, Redis et des cookies sécurisés', function () {
    expect(envExemple())->toMatchArray([
        'APP_DEBUG' => 'false',
        'APP_LOCALE' => 'fr',
        'DB_CONNECTION' => 'mysql',
        'SESSION_SECURE_COOKIE' => 'true',
        'SESSION_HTTP_ONLY' => 'true',
        'SESSION_SAME_SITE' => 'strict',
        'SESSION_ENCRYPT' => 'true',
        'QUEUE_CONNECTION' => 'redis',
    ]);
});

it('ne commite jamais le fichier .env', function () {
    expect(file_get_contents(base_path('.gitignore')))->toContain("\n.env\n");
});

it('refuse de lancer les tests sur une base qui n’est pas une base de test (CLAUDE.md §9)', function () {
    expect(fn () => TestCase::verifierBaseDeTest('mysql', 'microfinance'))
        ->toThrow(RuntimeException::class)
        ->and(fn () => TestCase::verifierBaseDeTest('mysql', 'microfinance_test'))->not->toThrow(RuntimeException::class)
        ->and(fn () => TestCase::verifierBaseDeTest('sqlite', ':memory:'))->not->toThrow(RuntimeException::class)
        ->and(fn () => TestCase::verifierBaseDeTest('sqlite', '/var/data/microfinance.sqlite'))->toThrow(RuntimeException::class);
});
