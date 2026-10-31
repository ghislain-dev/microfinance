<?php

declare(strict_types=1);

namespace App\Domain\Finance\Exceptions;

/**
 * La date de valeur tombe dans une période clôturée.
 */
final class PeriodeCloturee extends RefusFinancier {}
