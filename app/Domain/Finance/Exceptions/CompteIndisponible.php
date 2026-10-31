<?php

declare(strict_types=1);

namespace App\Domain\Finance\Exceptions;

/**
 * Compte introuvable, inactif, d'une autre devise ou d'une autre organisation.
 */
final class CompteIndisponible extends RefusFinancier {}
