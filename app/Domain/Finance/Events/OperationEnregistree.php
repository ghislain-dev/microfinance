<?php

declare(strict_types=1);

namespace App\Domain\Finance\Events;

use App\Domain\Finance\Models\Operation;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Émis une fois la transaction validée : les autres modules réagissent sans risque de fausse alerte.
 */
final class OperationEnregistree implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Operation $operation) {}
}
