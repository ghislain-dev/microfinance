<?php

declare(strict_types=1);

namespace App\Domain\Tontines\Events;

use App\Domain\Tontines\Models\Cycle;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class CycleDemarre implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Cycle $cycle) {}
}
