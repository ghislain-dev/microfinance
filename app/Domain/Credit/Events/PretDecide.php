<?php

declare(strict_types=1);

namespace App\Domain\Credit\Events;

use App\Domain\Credit\Models\Pret;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Décision finale sur un prêt : approuve | rejete | decaisse.
 */
final class PretDecide implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Pret $pret, public readonly string $decision) {}
}
