<?php

declare(strict_types=1);

namespace App\Domain\Finance\Exceptions;

/**
 * Le solde d'un compte qui ne peut pas être négatif deviendrait négatif.
 */
final class SoldeInsuffisant extends RefusFinancier {}
