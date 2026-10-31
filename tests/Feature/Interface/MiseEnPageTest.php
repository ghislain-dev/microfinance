<?php

declare(strict_types=1);

use App\Domain\Organisation\Models\Agence;
use Illuminate\Support\Str;
use Tests\Support\Acteurs;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

const MENU = ['Tableau de bord', 'Membres', 'Agences', 'Utilisateurs', 'Journal d’audit'];

it('affiche le menu selon le rôle', function (string $role, array $visibles) {
    actingAs(Acteurs::avecRole($role));

    $html = get('/tableau-de-bord')->assertOk()->getContent();
    $menu = strip_tags(Str::betweenFirst((string) $html, '<ul class="navbar-nav', '</ul>'));

    foreach (MENU as $entree) {
        in_array($entree, $visibles, true)
            ? expect($menu)->toContain($entree)
            : expect($menu)->not->toContain($entree);
    }
})->with([
    'admin' => ['admin', MENU],
    'gérant' => ['gerant', ['Tableau de bord', 'Membres', 'Agences', 'Utilisateurs', 'Journal d’audit']],
    'comptable' => ['comptable', ['Tableau de bord', 'Membres', 'Agences', 'Journal d’audit']],
    'caissier' => ['caissier', ['Tableau de bord', 'Membres']],
    'collecteur' => ['collecteur', ['Tableau de bord', 'Membres']],
    'agent de crédit' => ['agent_credit', ['Tableau de bord', 'Membres']],
    'comité' => ['comite', ['Tableau de bord', 'Membres']],
]);

it('affiche l’utilisateur, son organisation, ses rôles et ses agences', function () {
    $gerant = Acteurs::avecRole('gerant');
    $agence = Agence::factory()->for($gerant->organisation)->create(['nom' => 'Agence Ibanda']);
    $gerant->agences()->attach($agence, ['organisation_id' => $gerant->organisation_id]);
    actingAs($gerant);

    get('/tableau-de-bord')->assertOk()
        ->assertSeeText($gerant->name)
        ->assertSeeText($gerant->organisation->nom)
        ->assertSeeText('Gérant')
        ->assertSeeText('Agence Ibanda')
        ->assertSee('<html lang="fr">', false)
        ->assertSee('action="'.route('deconnexion').'"', false);
});

it('refuse le tableau de bord à un membre, même connecté (403)', function () {
    actingAs(Acteurs::avecRole('membre'));

    get('/tableau-de-bord')->assertForbidden();
});

it('échappe les données saisies par les utilisateurs', function () {
    $admin = Acteurs::avecRole('admin');
    $admin->update(['name' => '<script>alert(1)</script>']);
    actingAs($admin);

    get('/tableau-de-bord')->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
});

it('n’utilise jamais {!! !!} dans les vues (CLAUDE.md §5)', function () {
    $fautifs = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'))) as $fichier) {
        if ($fichier->isFile() && str_contains((string) file_get_contents($fichier->getPathname()), '{!!')) {
            $fautifs[] = $fichier->getPathname();
        }
    }

    expect($fautifs)->toBe([]);
});

it('ne charge aucune ressource depuis un CDN externe', function () {
    get('/connexion')->assertOk()->assertDontSee('https://', false);
});
