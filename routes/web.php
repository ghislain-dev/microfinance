<?php

declare(strict_types=1);

use App\Domain\Organisation\Acces\Droit;
use App\Http\Controllers\Web\Auth\CodeOtpController;
use App\Http\Controllers\Web\Auth\ConnexionController;
use App\Http\Controllers\Web\CaisseController;
use App\Http\Controllers\Web\ChangeController;
use App\Http\Controllers\Web\CompteEpargneController;
use App\Http\Controllers\Web\DemandeDecaissementController;
use App\Http\Controllers\Web\MembreController;
use App\Http\Controllers\Web\OperationEpargneController;
use App\Http\Controllers\Web\OrganisationController;
use App\Http\Controllers\Web\RecuController;
use App\Http\Controllers\Web\RemunerationController;
use App\Http\Controllers\Web\SessionCaisseController;
use App\Http\Controllers\Web\SeuilValidationController;
use App\Http\Controllers\Web\TypeCompteEpargneController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [ConnexionController::class, 'create'])->name('login');
    Route::post('/connexion', [ConnexionController::class, 'store'])->middleware('throttle:connexion');

    Route::get('/connexion/code', [CodeOtpController::class, 'create'])->name('connexion.code');
    Route::post('/connexion/code', [CodeOtpController::class, 'store'])->middleware('throttle:otp');
    Route::post('/connexion/code/renvoyer', [CodeOtpController::class, 'renvoyer'])
        ->middleware('throttle:otp')->name('connexion.code.renvoyer');
});

Route::middleware('auth')->group(function () {
    Route::post('/deconnexion', [ConnexionController::class, 'destroy'])->name('deconnexion');

    Route::redirect('/', '/tableau-de-bord');
    Route::view('/tableau-de-bord', 'tableau-de-bord')
        ->middleware('can:'.Droit::TableauDeBordVoir->value)->name('tableau-de-bord');

    Route::get('/membres', [MembreController::class, 'index'])->name('membres.index');
    Route::get('/membres/creer', [MembreController::class, 'create'])->name('membres.create');
    Route::post('/membres', [MembreController::class, 'store'])->name('membres.store');
    Route::get('/membres/{membre}', [MembreController::class, 'show'])->whereNumber('membre')->name('membres.show');
    Route::get('/membres/{membre}/modifier', [MembreController::class, 'edit'])->whereNumber('membre')->name('membres.edit');
    Route::put('/membres/{membre}', [MembreController::class, 'update'])->whereNumber('membre')->name('membres.update');
    Route::patch('/membres/{membre}/statut', [MembreController::class, 'changerStatut'])->whereNumber('membre')->name('membres.statut');

    Route::get('/caisses', [CaisseController::class, 'index'])->name('caisses.index');
    Route::get('/caisses/creer', [CaisseController::class, 'create'])->name('caisses.create');
    Route::post('/caisses', [CaisseController::class, 'store'])->name('caisses.store');
    Route::get('/caisses/{caisse}/ouvrir', [SessionCaisseController::class, 'create'])->whereNumber('caisse')->name('caisses.ouvrir');
    Route::post('/caisses/{caisse}/ouvrir', [SessionCaisseController::class, 'store'])->whereNumber('caisse');
    Route::get('/sessions-caisse/{sessionCaisse}', [SessionCaisseController::class, 'show'])->whereNumber('sessionCaisse')->name('sessions-caisse.show');
    Route::post('/sessions-caisse/{sessionCaisse}/cloturer', [SessionCaisseController::class, 'cloturer'])->whereNumber('sessionCaisse')->name('sessions-caisse.cloturer');
    Route::post('/sessions-caisse/{sessionCaisse}/valider', [SessionCaisseController::class, 'valider'])->whereNumber('sessionCaisse')->name('sessions-caisse.valider');

    Route::get('/epargne/types', [TypeCompteEpargneController::class, 'index'])->name('epargne.types.index');
    Route::get('/epargne/types/creer', [TypeCompteEpargneController::class, 'create'])->name('epargne.types.create');
    Route::post('/epargne/types', [TypeCompteEpargneController::class, 'store'])->name('epargne.types.store');
    Route::get('/epargne/types/{type}/modifier', [TypeCompteEpargneController::class, 'edit'])->whereNumber('type')->name('epargne.types.edit');
    Route::put('/epargne/types/{type}', [TypeCompteEpargneController::class, 'update'])->whereNumber('type')->name('epargne.types.update');
    Route::post('/membres/{membre}/comptes-epargne', [CompteEpargneController::class, 'store'])->whereNumber('membre')->name('membres.comptes-epargne.store');

    Route::get('/comptes-epargne/{compteEpargne}', [OperationEpargneController::class, 'show'])->whereNumber('compteEpargne')->name('comptes-epargne.show');
    Route::post('/comptes-epargne/{compteEpargne}/depots', [OperationEpargneController::class, 'deposer'])->whereNumber('compteEpargne')->name('comptes-epargne.depots');
    Route::post('/comptes-epargne/{compteEpargne}/retraits', [OperationEpargneController::class, 'retirer'])->whereNumber('compteEpargne')->name('comptes-epargne.retraits');
    Route::post('/comptes-epargne/{compteEpargne}/virements', [OperationEpargneController::class, 'virer'])->whereNumber('compteEpargne')->name('comptes-epargne.virements');
    Route::post('/comptes-epargne/{compteEpargne}/conversions', [OperationEpargneController::class, 'convertir'])->whereNumber('compteEpargne')->name('comptes-epargne.conversions');

    Route::get('/change', [ChangeController::class, 'index'])->name('change.index');
    Route::post('/change', [ChangeController::class, 'store']);

    Route::get('/demandes', [DemandeDecaissementController::class, 'index'])->name('demandes.index');
    Route::post('/demandes/{demande}/approuver', [DemandeDecaissementController::class, 'approuver'])->whereNumber('demande')->name('demandes.approuver');
    Route::post('/demandes/{demande}/rejeter', [DemandeDecaissementController::class, 'rejeter'])->whereNumber('demande')->name('demandes.rejeter');
    Route::post('/demandes/{demande}/payer', [OperationEpargneController::class, 'payer'])->whereNumber('demande')->name('demandes.payer');

    Route::get('/epargne/seuils', [SeuilValidationController::class, 'index'])->name('epargne.seuils');
    Route::get('/recus/{recu}', [RecuController::class, 'show'])->whereNumber('recu')->name('recus.show');

    Route::get('/epargne/remunerations', [RemunerationController::class, 'index'])->name('epargne.remunerations.index');
    Route::post('/epargne/remunerations/interets', [RemunerationController::class, 'interets'])->name('epargne.remunerations.interets');
    Route::post('/epargne/remunerations/ristournes', [RemunerationController::class, 'ristourne'])->name('epargne.remunerations.ristournes');
    Route::get('/epargne/remunerations/{lot}', [RemunerationController::class, 'show'])->whereNumber('lot')->name('epargne.remunerations.show');
    Route::post('/epargne/remunerations/{lot}/valider', [RemunerationController::class, 'valider'])->whereNumber('lot')->name('epargne.remunerations.valider');
    Route::post('/epargne/remunerations/{lot}/rejeter', [RemunerationController::class, 'rejeter'])->whereNumber('lot')->name('epargne.remunerations.rejeter');
    Route::get('/organisation', [OrganisationController::class, 'show'])->name('organisation.show');
    Route::post('/organisation/logo', [OrganisationController::class, 'logo'])->name('organisation.logo');
    Route::post('/epargne/seuils', [SeuilValidationController::class, 'store']);
});
