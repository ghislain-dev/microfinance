<?php

declare(strict_types=1);

use App\Domain\Credit\Actions\AnalyserPret;
use App\Domain\Credit\Actions\DecaisserPret;
use App\Domain\Credit\Actions\DeciderPret;
use App\Domain\Epargne\Actions\Deposer;
use App\Domain\Epargne\Actions\OuvrirCompteEpargne;
use App\Domain\Membres\Actions\CreerAccesMobile;
use App\Domain\Membres\Models\Membre;
use App\Domain\Notifications\Models\JetonPush;
use App\Domain\Organisation\Models\Organisation;
use App\Domain\Tontines\Actions\DemarrerCycle;
use App\Domain\Tontines\TypeGroupe;
use App\Models\User;
use App\Support\Montant\Devise;
use Illuminate\Support\Str;
use Tests\Support\Acteurs;
use Tests\Support\Credit;
use Tests\Support\Epargnant;
use Tests\Support\Guichet;
use Tests\Support\PushFactice;
use Tests\Support\Tontine;

use function Pest\Laravel\artisan;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travelTo;

/**
 * Accès mobile créé et jeton push enregistré pour le téléphone du membre.
 */
function membreJoignable(Membre $membre, User $guichetier, string $jetonPush): User
{
    [$utilisateur] = app(CreerAccesMobile::class)->executer($guichetier, $membre);
    $utilisateur->forceFill(['mot_de_passe_provisoire' => false])->save();
    (new JetonPush)->forceFill(['organisation_id' => $membre->organisation_id, 'user_id' => $utilisateur->id, 'jeton' => $jetonPush, 'plateforme' => 'android'])->save();

    return $utilisateur;
}

function rappels(): void
{
    $utilisateur = auth()->user();
    auth()->forgetUser(); // commande planifiée : aucun utilisateur connecté
    artisan('notifications:rappels')->assertSuccessful();
    if ($utilisateur !== null) {
        auth()->setUser($utilisateur);
    }
}

it('enregistre le jeton push du téléphone du membre', function () {
    $c = Credit::preparer();
    [$utilisateur] = app(CreerAccesMobile::class)->executer($c->g->caissier, $c->membre);
    $utilisateur->forceFill(['mot_de_passe_provisoire' => false])->save();
    $jeton = $utilisateur->createToken('t', ['membre'])->plainTextToken;
    auth()->forgetGuards();

    foreach ([1, 2] as $fois) {
        postJson('/api/v1/membre/notifications/jeton', ['jeton' => 'fcm-abc', 'plateforme' => 'android'], ['Authorization' => "Bearer {$jeton}"])->assertNoContent();
    }
    postJson('/api/v1/membre/notifications/jeton', ['jeton' => '', 'plateforme' => 'ios'], ['Authorization' => "Bearer {$jeton}"])->assertUnprocessable();

    expect(JetonPush::count())->toBe(1)->and(JetonPush::sole()->getAttribute('user_id'))->toBe($utilisateur->id);
});

it('notifie le membre à chaque opération sur son épargne, une seule fois', function () {
    $push = PushFactice::installer();
    $c = Credit::preparer();
    membreJoignable($c->membre, $c->g->caissier, 'fcm-1');
    $cle = (string) Str::uuid();

    app(Deposer::class)->executer($c->g->caissier, $c->compte, Credit::usd('25'), $cle);
    app(Deposer::class)->executer($c->g->caissier, $c->compte, Credit::usd('25'), $cle); // rejeu

    expect($push->envois)->toHaveCount(1)
        ->and($push->envois[0]['jeton'])->toBe('fcm-1')
        ->and($push->envois[0]['titre'])->toBe('Dépôt')
        ->and($push->envois[0]['corps'])->toContain('25,00 USD')->toContain('525,00 USD');
});

it('garde l’historique des notifications du membre (avec ?depuis=)', function () {
    $push = PushFactice::installer();
    $c = Credit::preparer();
    $utilisateur = membreJoignable($c->membre, $c->g->caissier, 'fcm-1');
    app(Deposer::class)->executer($c->g->caissier, $c->compte, Credit::usd('25'), (string) Str::uuid());
    $jeton = $utilisateur->createToken('t', ['membre'])->plainTextToken;
    auth()->forgetGuards();

    getJson('/api/v1/membre/notifications', ['Authorization' => "Bearer {$jeton}"])->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.titre', 'Dépôt');
    getJson('/api/v1/membre/notifications?depuis='.urlencode(now()->addMinute()->toIso8601String()), ['Authorization' => "Bearer {$jeton}"])
        ->assertOk()->assertJsonCount(0, 'data');
});

it('rappelle une échéance de prêt N jours avant, selon le paramètre de l’organisation', function () {
    $push = PushFactice::installer();
    travelTo('2026-01-15 09:00');
    $c = Credit::preparer();
    membreJoignable($c->membre, $c->g->caissier, 'fcm-1');
    app(DecaisserPret::class)->executer($c->comptable, $c->approuve('1000', 10));
    $push->vider();

    travelTo('2026-02-12 08:00');
    rappels();
    expect($push->envois)->toBeEmpty(); // aucun délai fixé par l'organisation

    Organisation::query()->whereKey($c->g->organisation->id)->update(['rappel_echeance_jours' => 3]);
    rappels();
    rappels();
    expect($push->titres())->toBe(['Échéance de prêt'])
        ->and($push->envois[0]['corps'])->toContain('15/02/2026')->toContain('130,00 USD');
});

it('annonce son tour au membre au démarrage du cycle, puis la veille de la séance où il reçoit le pot', function () {
    $push = PushFactice::installer();
    travelTo('2026-02-20 09:00');
    $g = Guichet::creer();
    [$groupe, $membres] = Tontine::groupe($g, TypeGroupe::Rotative, 2);
    $type = Epargnant::type($g->gerant, 'EPC', Devise::CDF);
    foreach ($membres as $i => $m) {
        app(OuvrirCompteEpargne::class)->executer($g->gerant, $m, $type);
        membreJoignable($m, $g->caissier, "fcm-{$i}");
    }
    $cycle = Tontine::cycle($g, $groupe, ['mode_tours' => 'ordre_fixe', 'date_debut' => '2026-03-02']);
    app(DemarrerCycle::class)->executer($g->gerant, $cycle, [$membres[1]->id, $membres[0]->id]);

    expect($push->titres())->toBe(['Votre tour de tontine', 'Votre tour de tontine']);
    $tour = collect($push->envois)->firstWhere('jeton', 'fcm-1');
    expect($tour['corps'] ?? '')->toContain('rang 1')->toContain('02/03/2026');

    $push->vider();
    travelTo('2026-03-01 18:00');
    rappels();
    rappels();
    expect($push->envois)->toHaveCount(1)->and($push->envois[0]['jeton'])->toBe('fcm-1')->and($push->envois[0]['titre'])->toBe('Demain, vous recevez le pot');
});

it('informe le membre de la décision sur son prêt et du décaissement', function () {
    $push = PushFactice::installer();
    $c = Credit::preparer();
    membreJoignable($c->membre, $c->g->caissier, 'fcm-1');
    $pret = $c->approuve('1000', 10);
    app(DecaisserPret::class)->executer($c->comptable, $pret);

    expect($push->titres())->toContain('Prêt approuvé')->toContain('Prêt décaissé');

    $autre = Credit::autreEmprunteur($c);
    membreJoignable($autre->membre, $c->g->caissier, 'fcm-2');
    $demande = $autre->demande('200');
    app(AnalyserPret::class)->executer($c->agent, $demande, 'Revenus faibles.');
    app(DeciderPret::class)->rejeter($c->comite, $demande, 'Capacité insuffisante');
    expect(collect($push->envois)->where('jeton', 'fcm-2')->pluck('titre')->all())->toContain('Demande de prêt rejetée');
});

it('oublie un jeton que Firebase déclare invalide', function () {
    $push = PushFactice::installer();
    $push->invalides = ['fcm-perime'];
    $c = Credit::preparer();
    membreJoignable($c->membre, $c->g->caissier, 'fcm-perime');

    app(Deposer::class)->executer($c->g->caissier, $c->compte, Credit::usd('5'), (string) Str::uuid());

    expect(JetonPush::count())->toBe(0);
});

it('règle le délai de rappel depuis l’écran Organisation (admin)', function () {
    $admin = Acteurs::avecRole('admin');
    Pest\Laravel\actingAs($admin);

    Pest\Laravel\post('/organisation/rappels', ['rappel_echeance_jours' => '45'])->assertSessionHasErrors('rappel_echeance_jours');
    Pest\Laravel\post('/organisation/rappels', ['rappel_echeance_jours' => '3'])->assertRedirect('/organisation');
    expect($admin->organisation()->firstOrFail()->getAttribute('rappel_echeance_jours'))->toBe(3);
    Pest\Laravel\post('/organisation/rappels', ['rappel_echeance_jours' => ''])->assertRedirect('/organisation');
    expect($admin->organisation()->firstOrFail()->getAttribute('rappel_echeance_jours'))->toBeNull();
});
