<?php

declare(strict_types=1);

namespace App\Domain\Caisse;

enum MomentComptage: string
{
    case Ouverture = 'ouverture';
    case Cloture = 'cloture';
}
