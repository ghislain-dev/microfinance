<?php

declare(strict_types=1);

namespace App\Support\Push;

/**
 * Envoi d'une notification push à un téléphone (jeton Firebase).
 */
interface EnvoyeurPush
{
    /**
     * @param  array<string, string>  $donnees  données lues par l'application (ex. type, identifiant)
     */
    public function envoyer(string $jeton, string $titre, string $corps, array $donnees = []): ResultatPush;
}
