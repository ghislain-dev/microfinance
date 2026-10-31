<?php

declare(strict_types=1);

use App\Domain\Finance\Comptes\NatureCompte;
use App\Domain\Finance\Comptes\Sens;

it('donne le sens normal de chaque nature', function (NatureCompte $nature, Sens $sens) {
    expect($nature->sens())->toBe($sens);
})->with([
    [NatureCompte::Caisse, Sens::Debit],
    [NatureCompte::Pret, Sens::Debit],
    [NatureCompte::CreanceMembre, Sens::Debit],
    [NatureCompte::CreancePersonnel, Sens::Debit],
    [NatureCompte::PositionChange, Sens::Debit],
    [NatureCompte::Charge, Sens::Debit],
    [NatureCompte::Epargne, Sens::Credit],
    [NatureCompte::PartsSociales, Sens::Credit],
    [NatureCompte::CycleTontine, Sens::Credit],
    [NatureCompte::FondsSocial, Sens::Credit],
    [NatureCompte::Produit, Sens::Credit],
    [NatureCompte::FondsPropres, Sens::Credit],
]);

it('interdit le solde négatif pour la caisse, l’épargne, les parts et les fonds propres', function () {
    $jamaisNegatifs = array_values(array_filter(NatureCompte::cases(), fn (NatureCompte $n) => ! $n->peutEtreNegatif()));

    expect($jamaisNegatifs)->toBe([NatureCompte::Caisse, NatureCompte::Epargne, NatureCompte::PartsSociales, NatureCompte::FondsPropres]);
});
