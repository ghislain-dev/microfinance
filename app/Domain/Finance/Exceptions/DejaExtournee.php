<?php

declare(strict_types=1);

namespace App\Domain\Finance\Exceptions;

/**
 * Une opération n'est extournée qu'une fois.
 */
final class DejaExtournee extends RefusFinancier {}
