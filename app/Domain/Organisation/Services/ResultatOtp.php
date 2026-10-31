<?php

declare(strict_types=1);

namespace App\Domain\Organisation\Services;

enum ResultatOtp
{
    case Valide;
    case Incorrect;
    /** Aucun code utilisable : expiré, déjà utilisé, essais épuisés ou jamais émis. */
    case AucunCode;
}
