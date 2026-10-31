<?php

declare(strict_types=1);

namespace App\Domain\Finance\Exceptions;

/**
 * Débits et crédits non équilibrés, ligne invalide ou devises mélangées.
 */
final class OperationDesequilibree extends RefusFinancier {}
