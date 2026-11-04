<?php

declare(strict_types=1);

namespace App\Domain\Comptabilite\Services;

use App\Support\Montant\Montant;

final readonly class GrandLivre
{
    /**
     * @param  list<LigneGrandLivre>  $lignes
     */
    public function __construct(
        public string $rubrique,
        public string $syscohada,
        public string $libelle,
        public Montant $ouverture,
        public array $lignes,
        public Montant $cloture,
    ) {}
}
