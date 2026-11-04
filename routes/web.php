<?php

declare(strict_types=1);

use App\Domain\Organisation\Acces\Droit;
use App\Http\Controllers\Web\Auth\CodeOtpController;
use App\Http\Controllers\Web\Auth\ConnexionController;
use App\Http\Controllers\Web\CaisseController;
use App\Http\Controllers\Web\ChangeController;
use App\Http\Controllers\Web\ClotureController;
use App\Http\Controllers\Web\CollecteController;
use App\Http\Controllers\Web\ComptabiliteController;
use App\Http\Controllers\Web\CompteEpargneController;
use App\Http\Controllers\Web\CycleController;
use App\Http\Controllers\Web\DemandeDecaissementController;
use App\Http\Controllers\Web\ExportController;
use App\Http\Controllers\Web\MembreController;
use App\Http\Controllers\Web\OperationEpargneController;
use App\Http\Controllers\Web\OrganisationController;
use App\Http\Controllers\Web\PretController;
use App\Http\Controllers\Web\ProduitCreditController;
use App\Http\Controllers\Web\RecuController;
use App\Http\Controllers\Web\RemunerationController;
use App\Http\Controllers\Web\SeanceController;
use App\Http\Controllers\Web\SessionCaisseController;
use App\Http\Controllers\Web\SeuilValidationController;
use App\Http\Controllers\Web\TableauDeBordController;
use App\Http\Controllers\Web\TontineController;
use App\Http\Controllers\Web\TypeCompteEpargneController;
use App\Http\Controllers\Web\VersementController;
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
    Route::get('/tableau-de-bord', TableauDeBordController::class)
        ->middleware('can:'.Droit::TableauDeBordVoir->value)->name('tableau-de-bord');

    Route::get('/membres', [MembreController::class, 'index'])->name('membres.index');
    Route::get('/membres/creer', [MembreController::class, 'create'])->name('membres.create');
    Route::post('/membres', [MembreController::class, 'store'])->name('membres.store');
    Route::get('/membres/{membre}', [MembreController::class, 'show'])->whereNumber('membre')->name('membres.show');
    Route::get('/membres/{membre}/modifier', [MembreController::class, 'edit'])->whereNumber('membre')->name('membres.edit');
    Route::put('/membres/{membre}', [MembreController::class, 'update'])->whereNumber('membre')->name('membres.update');
    Route::patch('/membres/{membre}/statut', [MembreController::class, 'changerStatut'])->whereNumber('membre')->name('membres.statut');
    Route::post('/membres/{membre}/acces-mobile', [MembreController::class, 'accesMobile'])->whereNumber('membre')->name('membres.acces-mobile');

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

    Route::get('/tontines', [TontineController::class, 'index'])->name('tontines.index');
    Route::post('/tontines', [TontineController::class, 'store'])->name('tontines.store');
    Route::get('/tontines/{groupe}', [TontineController::class, 'show'])->whereNumber('groupe')->name('tontines.show');
    Route::post('/tontines/{groupe}/membres', [TontineController::class, 'inscrire'])->whereNumber('groupe')->name('tontines.inscrire');
    Route::post('/tontines/{groupe}/cycles', [TontineController::class, 'creerCycle'])->whereNumber('groupe')->name('tontines.cycles.store');
    Route::get('/cycles/{cycle}', [CycleController::class, 'show'])->whereNumber('cycle')->name('cycles.show');
    Route::post('/cycles/{cycle}/demarrer', [CycleController::class, 'demarrer'])->whereNumber('cycle')->name('cycles.demarrer');
    Route::post('/cycles/{cycle}/aides', [CycleController::class, 'demanderAide'])->whereNumber('cycle')->name('cycles.aides');
    Route::post('/cycles/{cycle}/partage', [CycleController::class, 'calculerPartage'])->whereNumber('cycle')->name('cycles.partage');
    Route::post('/aides/{aide}/{decision}', [CycleController::class, 'deciderAide'])->whereNumber('aide')->whereIn('decision', ['valider', 'rejeter'])->name('aides.decider');
    Route::post('/partages/{partage}/{decision}', [CycleController::class, 'deciderPartage'])->whereNumber('partage')->whereIn('decision', ['valider', 'rejeter'])->name('partages.decider');
    Route::get('/seances/{seance}', [SeanceController::class, 'show'])->whereNumber('seance')->name('seances.show');
    Route::post('/seances/{seance}', [SeanceController::class, 'store'])->whereNumber('seance');
    Route::post('/organisation/logo', [OrganisationController::class, 'logo'])->name('organisation.logo');
    Route::post('/organisation/rappels', [OrganisationController::class, 'rappels'])->name('organisation.rappels');
    Route::post('/organisation/fuseau', [OrganisationController::class, 'fuseau'])->name('organisation.fuseau');
    Route::post('/epargne/seuils', [SeuilValidationController::class, 'store']);

    Route::get('/credit', [PretController::class, 'index'])->name('credit.index');
    Route::get('/credit/produits', [ProduitCreditController::class, 'index'])->name('credit.produits.index');
    Route::get('/credit/produits/creer', [ProduitCreditController::class, 'create'])->name('credit.produits.create');
    Route::post('/credit/produits', [ProduitCreditController::class, 'store'])->name('credit.produits.store');
    Route::get('/credit/produits/{produit}/modifier', [ProduitCreditController::class, 'edit'])->whereNumber('produit')->name('credit.produits.edit');
    Route::put('/credit/produits/{produit}', [ProduitCreditController::class, 'update'])->whereNumber('produit')->name('credit.produits.update');
    Route::post('/credit/seuils', [ProduitCreditController::class, 'seuil'])->name('credit.seuils');
    Route::get('/credit/demandes/creer', [PretController::class, 'create'])->name('credit.demandes.create');
    Route::post('/credit/demandes', [PretController::class, 'store'])->name('credit.demandes.store');
    Route::get('/prets/{pret}', [PretController::class, 'show'])->whereNumber('pret')->name('prets.show');
    Route::post('/prets/{pret}/analyse', [PretController::class, 'analyser'])->whereNumber('pret')->name('prets.analyse');
    Route::post('/prets/{pret}/decaissement', [PretController::class, 'decaisser'])->whereNumber('pret')->name('prets.decaissement');
    Route::post('/prets/{pret}/remboursements', [PretController::class, 'rembourser'])->whereNumber('pret')->name('prets.remboursements');
    Route::post('/prets/{pret}/{decision}', [PretController::class, 'decider'])->whereNumber('pret')->whereIn('decision', ['approuver', 'rejeter'])->name('prets.decider');
    Route::get('/collecte', [CollecteController::class, 'index'])->name('collecte.index');
    Route::post('/collecte/parametres', [CollecteController::class, 'parametres'])->name('collecte.parametres');
    Route::post('/collecte/appareils/{appareil}/{decision}', [CollecteController::class, 'appareil'])->whereNumber('appareil')
        ->whereIn('decision', ['autoriser', 'revoquer'])->name('collecte.appareil');
    Route::post('/collecte/{collecteur}/tournee', [CollecteController::class, 'affecter'])->whereNumber('collecteur')->name('collecte.affecter');
    Route::post('/collecte/{collecteur}/tournee/{membre}/retirer', [CollecteController::class, 'retirer'])->whereNumber(['collecteur', 'membre'])->name('collecte.retirer');
    Route::get('/versements', [VersementController::class, 'index'])->name('versements.index');
    Route::post('/versements', [VersementController::class, 'store'])->name('versements.store');
    Route::post('/versements/{versement}/valider', [VersementController::class, 'valider'])->whereNumber('versement')->name('versements.valider');
    Route::get('/comptabilite/balance', [ComptabiliteController::class, 'balance'])->name('comptabilite.balance');
    Route::get('/comptabilite/grand-livre', [ComptabiliteController::class, 'grandLivre'])->name('comptabilite.grand-livre');
    Route::get('/comptabilite/journal', [ComptabiliteController::class, 'journal'])->name('comptabilite.journal');
    Route::get('/comptabilite/plan', [ComptabiliteController::class, 'plan'])->name('comptabilite.plan');
    Route::post('/comptabilite/plan', [ComptabiliteController::class, 'modifierPlan']);
    Route::get('/comptabilite/clotures', [ClotureController::class, 'index'])->name('comptabilite.clotures');
    Route::post('/comptabilite/clotures', [ClotureController::class, 'store']);
    Route::post('/comptabilite/clotures/{demande}/{decision}', [ClotureController::class, 'decider'])->whereNumber('demande')
        ->whereIn('decision', ['valider', 'rejeter'])->name('comptabilite.clotures.decider');
    Route::get('/exports', [ExportController::class, 'index'])->name('exports.index');
    Route::post('/exports', [ExportController::class, 'store']);
    Route::get('/exports/{export}/fichier', [ExportController::class, 'fichier'])->whereNumber('export')->name('exports.fichier');
    Route::get('/garanties/{garantie}/piece', [PretController::class, 'piece'])->whereNumber('garantie')->name('garanties.piece');
});
