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
    [NatureCompte::Charge, Sens::Debit],
    [NatureCompte::Epargne, Sens::Credit],
    [NatureCompte::PartsSociales, Sens::Credit],
    [NatureCompte::CycleTontine, Sens::Credit],
    [NatureCompte::FondsSocial, Sens::Credit],
    [NatureCompte::Produit, Sens::Credit],
]);

it('interdit le solde négatif pour la caisse, l’épargne et les parts (décision du 2026-10-31)', function () {
    $jamaisNegatifs = array_values(array_filter(NatureCompte::cases(), fn (NatureCompte $n) => ! $n->peutEtreNegatif()));

    expect($jamaisNegatifs)->toBe([NatureCompte::Caisse, NatureCompte::Epargne, NatureCompte::PartsSociales]);
});
