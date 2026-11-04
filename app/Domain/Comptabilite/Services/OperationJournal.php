<?php

declare(strict_types=1);

namespace App\Domain\Comptabilite\Services;

use Carbon\CarbonImmutable;

final readonly class OperationJournal
{
    /**
     * @param  list<EcritureJournal>  $ecritures
     */
    public function __construct(
        public int $id,
        public CarbonImmutable $date,
        public string $type,
        public ?string $motif,
        public array $ecritures,
    ) {}
}
