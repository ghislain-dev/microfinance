<?php

declare(strict_types=1);

namespace App\Domain\Finance\Exceptions;

use DomainException;

/**
 * Opération refusée par une règle d'argent : rien n'a été écrit.
 */
abstract class RefusFinancier extends DomainException {}
