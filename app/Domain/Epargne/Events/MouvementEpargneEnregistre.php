<?php

declare(strict_types=1);

namespace App\Domain\Epargne\Events;

use App\Domain\Epargne\Models\MouvementEpargne;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Nouveau mouvement sur un compte d'épargne (diffusé après validation de la transaction).
 */
final class MouvementEpargneEnregistre implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly MouvementEpargne $mouvement) {}
}
