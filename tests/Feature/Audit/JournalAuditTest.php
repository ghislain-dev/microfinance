<?php

declare(strict_types=1);

use App\Domain\Organisation\Actions\AttribuerRoles;
use App\Domain\Organisation\Models\Agence;
use App\Models\User;
use App\Support\Audit\JournalAudit;
use App\Support\Audit\LigneAudit;
use App\Support\Audit\LigneAuditImmuable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Acteurs;
use Tests\Support\Connexion;
use Tests\Support\DeuxOrganisations;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

function derniereLigne(string $action): LigneAudit
{
    return LigneAudit::query()->withoutGlobalScopes()->where('action', $action)->latest('id')->firstOrFail();
}

it('refuse toute modification ou suppression par le modèle', function () {
    $ligne = app(JournalAudit::class)->enregistrer('essai');

    expect(fn () => $ligne->update(['action' => 'falsifie']))->toThrow(LigneAuditImmuable::class)
        ->and(fn () => $ligne->delete())->toThrow(LigneAuditImmuable::class)
        ->and(LigneAudit::withoutGlobalScopes()->where('action', 'essai')->count())->toBe(1);
});

it('refuse toute modification ou suppression en SQL direct (trigger)', function (string $sql) {
    app(JournalAudit::class)->enregistrer('essai');

    expect(fn () => DB::statement($sql))->toThrow(QueryException::class)
        ->and(DB::table('audit_logs')->where('action', 'essai')->count())->toBe(1);
})->with([
    'UPDATE' => "update audit_logs set action = 'falsifie'",
    'DELETE' => 'delete from audit_logs',
]);

it('journalise une connexion réussie avec IP et appareil', function () {
    Notification::fake();
    $caissier = Acteurs::avecRole('caissier');

    post('/connexion', ['email' => $caissier->email, 'password' => 'password'], ['User-Agent' => 'Firefox Kinshasa']);
    post('/connexion/code', ['code' => Connexion::dernierCode($caissier)], ['User-Agent' => 'Firefox Kinshasa']);

    $ligne = derniereLigne('connexion');
    expect($ligne->user_id)->toBe($caissier->id)
        ->and($ligne->organisation_id)->toBe($caissier->organisation_id)
        ->and($ligne->ip)->toBe('127.0.0.1')
        ->and($ligne->appareil)->toBe('Firefox Kinshasa')
        ->and($ligne->created_at)->not->toBeNull();
});

it('journalise un échec de connexion et un mauvais code', function () {
    Notification::fake();
    $caissier = Acteurs::avecRole('caissier');

    post('/connexion', ['email' => $caissier->email, 'password' => 'faux']);
    post('/connexion', ['email' => $caissier->email, 'password' => 'password']);
    $code = Connexion::dernierCode($caissier);
    post('/connexion/code', ['code' => $code === '000000' ? '111111' : '000000']);

    expect(derniereLigne('connexion.echec')->user_id)->toBe($caissier->id)
        ->and(derniereLigne('connexion.echec')->apres)->toBe(['email' => $caissier->email])
        ->and(derniereLigne('otp.echec')->user_id)->toBe($caissier->id);
});

it('journalise la déconnexion', function () {
    $caissier = Acteurs::avecRole('caissier');
    actingAs($caissier);

    post('/deconnexion');

    expect(derniereLigne('deconnexion')->user_id)->toBe($caissier->id);
});

it('journalise création et modification avec avant / après', function () {
    $admin = Acteurs::avecRole('admin');
    actingAs($admin);

    $agence = Agence::create(['nom' => 'Agence Kasavubu', 'code' => 'KAS']);
    $agence->update(['nom' => 'Agence Kasa-Vubu']);

    $creation = derniereLigne('agences.creation');
    $modification = derniereLigne('agences.modification');
    expect($creation->user_id)->toBe($admin->id)
        ->and($creation->cible_type)->toBe('agences')
        ->and($creation->cible_id)->toBe($agence->id)
        ->and($creation->apres)->toMatchArray(['nom' => 'Agence Kasavubu', 'code' => 'KAS'])
        ->and($modification->avant)->toBe(['nom' => 'Agence Kasavubu'])
        ->and($modification->apres)->toBe(['nom' => 'Agence Kasa-Vubu']);
});

it('ne garde jamais un mot de passe, un jeton ou un code dans le journal', function () {
    $admin = Acteurs::avecRole('admin');
    actingAs($admin);
    $collegue = User::factory()->for($admin->organisation)->create();

    $collegue->update(['password' => 'nouveau-mot-de-passe']);

    $ligne = derniereLigne('users.modification');
    expect($ligne->avant)->toBe(['password' => '[masqué]'])
        ->and($ligne->apres)->toBe(['password' => '[masqué]'])
        ->and(json_encode(LigneAudit::withoutGlobalScopes()->get()->toArray()))->not->toContain('$2y$');
});

it('journalise le changement de rôles', function () {
    $admin = Acteurs::avecRole('admin');
    $collegue = Acteurs::avecRole('caissier', $admin->organisation);
    actingAs($admin);

    (new AttribuerRoles)->executer($admin, $collegue, ['comptable']);

    $ligne = derniereLigne('roles.modification');
    expect($ligne->cible_id)->toBe($collegue->id)
        ->and($ligne->avant)->toBe(['roles' => ['caissier']])
        ->and($ligne->apres)->toBe(['roles' => ['comptable']]);
});

it('journalise la création et la révocation d’un jeton mobile', function () {
    Notification::fake();
    $collecteur = Acteurs::avecRole('collecteur');
    $demande = ['email' => $collecteur->email, 'password' => 'password', 'appareil' => 'Itel A70'];
    postJson('/api/v1/auth/jetons', $demande);
    $jeton = postJson('/api/v1/auth/jetons', [...$demande, 'code' => Connexion::dernierCode($collecteur)])->json('jeton');
    $id = getJson('/api/v1/appareils', ['Authorization' => "Bearer {$jeton}"])->json('data.0.id');

    deleteJson("/api/v1/appareils/{$id}", [], ['Authorization' => "Bearer {$jeton}"]);

    expect(derniereLigne('jeton.creation')->apres)->toBe(['appareil' => 'Itel A70'])
        ->and(derniereLigne('jeton.creation')->user_id)->toBe($collecteur->id)
        ->and(derniereLigne('jeton.revocation')->cible_id)->toBe($id);
});

it('ne montre que le journal de son organisation', function () {
    $o = DeuxOrganisations::creer();
    actingAs($o->userB);
    Agence::create(['nom' => 'Agence B2', 'code' => 'B2']);
    actingAs($o->userA);
    Agence::create(['nom' => 'Agence A2', 'code' => 'A2']);

    expect(LigneAudit::pluck('organisation_id')->unique()->values()->all())->toBe([$o->orgA->id]);
});
