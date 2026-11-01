<?php

declare(strict_types=1);

namespace App\Domain\Credit;

/**
 * Méthode de l'échéancier, choisie à chaque prêt (décision du 2026-11-01).
 */
enum MethodeCalcul: string
{
    case Lineaire = 'lineaire';   // capital constant
    case Degressif = 'degressif'; // annuité constante

    public function libelle(): string
    {
        return match ($this) {
            self::Lineaire => 'Linéaire (capital constant)',
            self::Degressif => 'Dégressif (échéance constante)',
        };
    }
}
