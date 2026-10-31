<?php

declare(strict_types=1);

use App\Domain\Membres\Actions\ChangerStatutMembre;
use App\Domain\Membres\Actions\InscrireMembre;
use App\Domain\Membres\Actions\ModifierMembre;
use App\Domain\Membres\Exceptions\PieceDejaEnregistree;
use App\Domain\Membres\Exceptions\TelephoneDejaUtilise;
use App\Domain\Membres\Models\Membre;
use App\Domain\Membres\StatutMembre;
use App\Domain\Organisation\Models\Agence;
use App\Models\User;
use App\Support\Audit\LigneAudit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Tests\Support\Acteurs;

use function Pest\Laravel\actingAs;

/**
 * @param  array<string, mixed>  $autres
 * @return array<string, mixed>
 */
function fiche(array $autres = []): array
{
    static $n = 0;
    $n++;

    return [
        'nom' => 'Kahindo',
        'postnom' => 'Masika',
        'prenom' => 'Esther',
        'sexe' => 'F',
        'date_naissance' => '1990-05-12',
        'telephone' => '08100000'.str_pad((string) $n, 2, '0', STR_PAD_LEFT),
        'piece_type' => 'Carte d’électeur',
        'piece_numero' => 'CE-'.str_pad((string) $n, 6, '0', STR_PAD_LEFT),
        'adresse' => 'Goma, Himbi, avenue du Lac 12',
        ...$autres,
    ];
}

/**
 * @return array{User, Agence}
 */
function guichet(string $role = 'caissier', string $codeAgence = 'BUK'): array
{
    $caissier = Acteurs::avecRole($role);
    $agence = Agence::factory()->for($caissier->organisation)->create(['code' => $codeAgence]);
    actingAs($caissier);

    return [$caissier, $agence];
}

it('inscrit un membre avec un numéro séquentiel par agence', function () {
    [$caissier, $buk] = guichet();
    $gom = Agence::factory()->for($caissier->organisation)->create(['code' => 'GOM']);
    $inscrire = app(InscrireMembre::class);

    $m1 = $inscrire->executer($caissier, $buk, fiche());
    $m2 = $inscrire->executer($caissier, $buk, fiche());
    $m3 = $inscrire->executer($caissier, $gom, fiche());

    expect([$m1->numero, $m2->numero, $m3->numero])->toBe(['BUK-000001', 'BUK-000002', 'GOM-000001'])
        ->and($m1->statut)->toBe(StatutMembre::Actif)
        ->and($m1->organisation_id)->toBe($caissier->organisation_id)
        ->and($m1->telephone)->toBe('+243810000001')
        ->and($m1->date_naissance->toDateString())->toBe('1990-05-12');
});

it('chiffre le numéro de pièce en base et le relit en clair', function () {
    [$caissier, $agence] = guichet();

    $membre = app(InscrireMembre::class)->executer($caissier, $agence, fiche(['piece_numero' => 'PASS-778899']));

    $brut = (string) DB::table('membres')->where('id', $membre->id)->value('piece_numero');
    expect($brut)->not->toContain('778899')
        ->and($membre->fresh()?->piece_numero)->toBe('PASS-778899');
});

it('refuse une pièce déjà enregistrée dans l’organisation (casse et espaces ignorés)', function () {
    [$caissier, $agence] = guichet();
    $inscrire = app(InscrireMembre::class);
    $premier = $inscrire->executer($caissier, $agence, fiche(['piece_type' => 'Passeport', 'piece_numero' => 'OP 123456']));

    try {
        $inscrire->executer($caissier, $agence, fiche(['piece_type' => 'passeport', 'piece_numero' => 'op123456']));
        throw new RuntimeException('Doublon de pièce accepté');
    } catch (PieceDejaEnregistree $e) {
        expect($e->membreExistant->id)->toBe($premier->id);
    }
    expect(Membre::count())->toBe(1);
});

it('bloque aussi en base une pièce en double', function () {
    [$caissier, $agence] = guichet();
    $membre = app(InscrireMembre::class)->executer($caissier, $agence, fiche());

    $copie = (array) DB::table('membres')->where('id', $membre->id)
        ->first(['organisation_id', 'agence_id', 'piece_empreinte', 'piece_type', 'piece_numero', 'nom', 'sexe', 'date_naissance', 'telephone', 'statut']);

    DB::table('membres')->insert([...$copie, 'numero' => 'BUK-999999', 'numero_sequence' => 999999]);
})->throws(UniqueConstraintViolationException::class);

it('accepte la même pièce dans une autre organisation', function () {
    [$caissierA, $agenceA] = guichet();
    app(InscrireMembre::class)->executer($caissierA, $agenceA, fiche(['piece_numero' => 'X-1']));
    auth()->forgetUser();
    [$caissierB, $agenceB] = guichet();

    expect(app(InscrireMembre::class)->executer($caissierB, $agenceB, fiche(['piece_numero' => 'X-1']))->exists)->toBeTrue();
});

it('signale un téléphone déjà utilisé, et inscrit après confirmation', function () {
    [$caissier, $agence] = guichet();
    $inscrire = app(InscrireMembre::class);
    $mere = $inscrire->executer($caissier, $agence, fiche(['telephone' => '0991234567']));

    try {
        $inscrire->executer($caissier, $agence, fiche(['telephone' => '+243 99 123 4567']));
        throw new RuntimeException('Téléphone partagé non signalé');
    } catch (TelephoneDejaUtilise $e) {
        expect($e->membres->pluck('id')->all())->toBe([$mere->id]);
    }

    $enfant = $inscrire->executer($caissier, $agence, fiche(['telephone' => '0991234567']), telephonePartageConfirme: true);
    expect($enfant->telephone)->toBe($mere->telephone);
});

it('modifie une fiche sans toucher au numéro, avec les mêmes contrôles de doublon', function () {
    [$caissier, $agence] = guichet();
    $inscrire = app(InscrireMembre::class);
    $a = $inscrire->executer($caissier, $agence, fiche(['piece_numero' => 'A-1']));
    $b = $inscrire->executer($caissier, $agence, fiche(['piece_numero' => 'B-1']));

    $modifie = app(ModifierMembre::class)->executer($caissier, $a, fiche(['nom' => 'Kavira', 'piece_numero' => 'A-1']));
    expect($modifie->nom)->toBe('Kavira')->and($modifie->numero)->toBe('BUK-000001');

    app(ModifierMembre::class)->executer($caissier, $b, fiche(['piece_numero' => 'a 1']));
})->throws(PieceDejaEnregistree::class);

it('change le statut et le journalise', function (StatutMembre $statut) {
    [$caissier, $agence] = guichet('gerant');
    $membre = app(InscrireMembre::class)->executer($caissier, $agence, fiche());

    app(ChangerStatutMembre::class)->executer($caissier, $membre, $statut);

    expect($membre->fresh()?->statut)->toBe($statut)
        ->and(LigneAudit::where('action', 'membres.modification')->latest('id')->first()?->apres)
        ->toBe(['statut' => $statut->value]);
})->with([StatutMembre::Suspendu, StatutMembre::Demissionnaire, StatutMembre::Exclu, StatutMembre::Decede]);

it('connaît exactement les 5 statuts validés', function () {
    expect(array_map(fn (StatutMembre $s) => $s->libelle(), StatutMembre::cases()))
        ->toBe(['Actif', 'Suspendu', 'Démissionnaire', 'Exclu', 'Décédé']);
});

it('ne garde jamais le numéro de pièce dans le journal d’audit', function () {
    [$caissier, $agence] = guichet();
    app(InscrireMembre::class)->executer($caissier, $agence, fiche(['piece_numero' => 'SECRET-42']));

    $ligne = LigneAudit::where('action', 'membres.creation')->sole();
    expect($ligne->apres)->toMatchArray(['piece_numero' => '[masqué]', 'piece_empreinte' => '[masqué]'])
        ->and(json_encode($ligne->apres))->not->toContain('SECRET-42');
});

it('refuse l’inscription aux rôles sans le droit, et dans l’agence d’une autre organisation', function () {
    [, $agence] = guichet();
    $collecteur = Acteurs::avecRole('collecteur', $agence->organisation);
    expect(fn () => app(InscrireMembre::class)->executer($collecteur, $agence, fiche()))->toThrow(AuthorizationException::class);

    auth()->forgetUser();
    [$caissierB] = guichet('caissier', 'XYZ');
    expect(fn () => app(InscrireMembre::class)->executer($caissierB, $agence, fiche()))->toThrow(AuthorizationException::class);
});

it('rend un 404 sur le membre d’une autre organisation (scope)', function () {
    [$caissier, $agence] = guichet();
    $membre = app(InscrireMembre::class)->executer($caissier, $agence, fiche());
    auth()->forgetUser();
    guichet();

    expect(Membre::find($membre->id))->toBeNull();
});
