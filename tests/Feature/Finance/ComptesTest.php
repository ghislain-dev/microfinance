<?php

declare(strict_types=1);

use App\Domain\Finance\Actions\OuvrirCompte;
use App\Domain\Finance\Comptes\NatureCompte;
use App\Domain\Finance\Models\Compte;
use App\Domain\Organisation\Models\Agence;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use App\Support\MultiOrganisation\OrganisationIncoherente;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Tests\Support\DeuxOrganisations;

use function Pest\Laravel\actingAs;

it('ouvre un compte à solde nul dans l’organisation de l’utilisateur', function () {
    $o = DeuxOrganisations::creer();
    actingAs($o->userA);

    $compte = (new OuvrirCompte)->executer('CAI-001', 'Caisse principale', NatureCompte::Caisse, Devise::CDF, $o->agenceA, syscohada: '571');

    expect($compte->organisation_id)->toBe($o->orgA->id)
        ->and($compte->agence_id)->toBe($o->agenceA->id)
        ->and($compte->nature)->toBe(NatureCompte::Caisse)
        ->and($compte->devise)->toBe(Devise::CDF)
        ->and($compte->syscohada)->toBe('571')
        ->and($compte->actif)->toBeTrue()
        ->and($compte->solde_cache)->toBeInstanceOf(Montant::class)
        ->and($compte->solde_cache->egal(Montant::zero(Devise::CDF)))->toBeTrue();
});

it('refuse deux comptes de même numéro dans une organisation, l’accepte dans deux', function () {
    $o = DeuxOrganisations::creer();
    actingAs($o->userB);
    (new OuvrirCompte)->executer('EP-1', 'Épargne', NatureCompte::Epargne, Devise::USD);
    actingAs($o->userA);
    (new OuvrirCompte)->executer('EP-1', 'Épargne', NatureCompte::Epargne, Devise::USD);
    expect(Compte::withoutGlobalScopes()->where('numero', 'EP-1')->count())->toBe(2);

    (new OuvrirCompte)->executer('EP-1', 'Doublon', NatureCompte::Epargne, Devise::USD);
})->throws(UniqueConstraintViolationException::class);

it('refuse une agence d’une autre organisation', function () {
    $o = DeuxOrganisations::creer();
    actingAs($o->userA);

    (new OuvrirCompte)->executer('CAI-9', 'Caisse', NatureCompte::Caisse, Devise::USD, $o->agenceB);
})->throws(OrganisationIncoherente::class);

it('bloque aussi en base un compte rattaché à l’agence d’une autre organisation', function () {
    $o = DeuxOrganisations::creer();

    DB::table('comptes')->insert([
        'organisation_id' => $o->orgA->id, 'agence_id' => $o->agenceB->id, 'numero' => 'X', 'libelle' => 'X',
        'nature' => 'caisse', 'devise' => 'USD', 'solde_cache' => 0, 'actif' => true,
    ]);
})->throws(QueryException::class);

it('interdit d’écrire le solde par assignation de masse (seul le moteur le met à jour)', function () {
    actingAs(DeuxOrganisations::creer()->userA);

    Compte::create(['numero' => 'Z', 'libelle' => 'Z', 'nature' => 'caisse', 'devise' => 'USD', 'solde_cache' => 1000]);
})->throws(MassAssignmentException::class);

it('lit et écrit un Montant via MontantCast (centimes + devise du compte)', function () {
    $o = DeuxOrganisations::creer();
    actingAs($o->userA);
    $compte = (new OuvrirCompte)->executer('CAI-2', 'Caisse', NatureCompte::Caisse, Devise::USD);

    $compte->solde_cache = new Montant(123456, Devise::USD);
    $compte->save();

    expect(DB::table('comptes')->where('id', $compte->id)->value('solde_cache'))->toBe(123456)
        ->and($compte->fresh()?->solde_cache->formate())->toBe('1 234,56 USD');
    expect(fn () => $compte->solde_cache = new Montant(1, Devise::CDF))->toThrow(LogicException::class);
});

it('ne compte pas l’agence comme obligatoire (comptes du siège)', function () {
    actingAs(DeuxOrganisations::creer()->userA);

    expect((new OuvrirCompte)->executer('PROD-1', 'Produits divers', NatureCompte::Produit, Devise::USD)->agence_id)->toBeNull()
        ->and(Agence::count())->toBe(1);
});
