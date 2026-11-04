<?php

declare(strict_types=1);

use function Pest\Laravel\post;

/**
 * @param  array<string, mixed>  $messages
 * @return list<string>
 */
function clesDeTraduction(array $messages, string $prefixe = ''): array
{
    $cles = [];
    foreach ($messages as $cle => $valeur) {
        $cles = [...$cles, ...(is_array($valeur) ? clesDeTraduction($valeur, "{$prefixe}{$cle}.") : ["{$prefixe}{$cle}"])];
    }

    return $cles;
}

it('traduit en français chaque message standard de Laravel', function (string $fichier) {
    $anglais = require base_path("vendor/laravel/framework/src/Illuminate/Translation/lang/en/{$fichier}.php");
    $francais = require lang_path("fr/{$fichier}.php");

    expect(array_diff(clesDeTraduction($anglais), clesDeTraduction($francais)))->toBe([]);
})->with(['validation', 'auth', 'passwords', 'pagination']);

it('affiche les erreurs de validation en français, avec des noms de champs lisibles', function () {
    expect(config('app.locale'))->toBe('fr')
        ->and(validator(['email' => ''], ['email' => 'required'])->errors()->first('email'))->toBe('Le champ adresse e-mail est obligatoire.')
        ->and(validator(['email' => 'x'], ['email' => 'email'])->errors()->first('email'))->toBe('Le champ adresse e-mail doit être une adresse e-mail valide.')
        ->and(validator(['nom' => str_repeat('a', 101)], ['nom' => 'max:100'])->errors()->first('nom'))->toBe('Le champ nom ne doit pas dépasser 100 caractères.')
        ->and(validator(['du' => '2026-02-01', 'au' => '2026-01-01'], ['au' => 'after_or_equal:du'])->errors()->first('au'))
        ->toBe('Le champ au doit être une date postérieure ou égale au champ du.')
        ->and(__('pagination.next'))->toBe('Suivant &raquo;')
        ->and(__('auth.throttle', ['seconds' => 30]))->toBe('Trop de tentatives de connexion. Réessayez dans 30 secondes.');
});

it('ne montre jamais une clé de traduction brute à l’écran', function () {
    post('/connexion', [])->assertSessionHasErrors('email');

    expect((string) session('errors')?->first('email'))->not->toStartWith('validation.')->toContain('obligatoire');
});
