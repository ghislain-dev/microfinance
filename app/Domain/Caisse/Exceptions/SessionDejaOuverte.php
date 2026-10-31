<?php

declare(strict_types=1);

namespace App\Domain\Caisse\Exceptions;

use DomainException;

/**
 * Une seule session active par caisse et par caissier.
 */
final class SessionDejaOuverte extends DomainException {}
