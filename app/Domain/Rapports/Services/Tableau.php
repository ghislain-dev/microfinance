<?php

declare(strict_types=1);

namespace App\Domain\Rapports\Services;

/**
 * Rapport mis en forme une fois, écrit ensuite en CSV ou en PDF. Les montants sont déjà formatés
 * (calculés en centimes entiers par les services métier).
 */
final readonly class Tableau
{
    /**
     * @param  list<string>  $colonnes
     * @param  list<list<string>>  $lignes
     * @param  list<int>  $aDroite  index des colonnes de montants
     */
    public function __construct(
        public string $titre,
        public string $sousTitre,
        public array $colonnes,
        public array $lignes,
        public array $aDroite = [],
    ) {}
}
