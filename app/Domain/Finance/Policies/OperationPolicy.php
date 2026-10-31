<?php

declare(strict_types=1);

namespace App\Domain\Finance\Policies;

use App\Domain\Finance\Models\Operation;
use App\Domain\Organisation\Acces\Droit;
use App\Models\User;

class OperationPolicy
{
    /**
     * 4 yeux (CLAUDE.md §4.7) : gérant ou comptable de l'organisation, jamais l'auteur de l'opération.
     */
    public function extourner(User $validateur, Operation $operation): bool
    {
        return $validateur->organisation_id === $operation->organisation_id
            && $validateur->id !== $operation->auteur_id
            && $validateur->can(Droit::OperationsExtourner->value);
    }
}
