<?php

declare(strict_types=1);

namespace App\Domain\Epargne\Exceptions;

use DomainException;

/**
 * Opération d'épargne refusée par une règle (membre non actif, type inactif, compte bloqué…).
 */
final class CompteEpargneRefuse extends DomainException {}
