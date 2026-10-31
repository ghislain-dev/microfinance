<?php

declare(strict_types=1);

namespace App\Support\MultiOrganisation;

use LogicException;

/**
 * Tentative d'écrire une ligne dans une autre organisation que celle de l'utilisateur,
 * ou de lier deux lignes de deux organisations différentes.
 */
final class OrganisationIncoherente extends LogicException {}
