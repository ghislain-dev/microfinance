<?php

declare(strict_types=1);

namespace App\Support\Push;

use Illuminate\Support\Facades\Log;

/**
 * Pilote par défaut tant que Firebase n'est pas configuré : écrit dans le journal, n'envoie rien.
 */
final class JournalPush implements EnvoyeurPush
{
    public function envoyer(string $jeton, string $titre, string $corps, array $donnees = []): ResultatPush
    {
        Log::info('Notification push non envoyée (Firebase non configuré)', [
            'jeton' => substr($jeton, 0, 6).'…',
            'titre' => $titre,
            'corps' => app()->environment('local') ? $corps : '[masqué]',
        ]);

        return ResultatPush::Envoye;
    }
}
