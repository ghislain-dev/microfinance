<?php

declare(strict_types=1);

namespace App\Domain\Caisse\Exceptions;

use DomainException;

/**
 * Action impossible dans l'état actuel de la session (ex. clôturer une session en écart).
 */
final class SessionInvalide extends DomainException {}
