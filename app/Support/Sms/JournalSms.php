<?php

declare(strict_types=1);

namespace App\Support\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Pilote par défaut tant qu'aucun fournisseur n'est choisi : écrit dans le journal, n'envoie rien.
 * Le contenu (qui peut contenir un code) n'est journalisé que sur le poste de développement.
 */
final class JournalSms implements EnvoyeurSms
{
    public function envoyer(string $telephone, string $message): void
    {
        Log::info('SMS non envoyé (aucun fournisseur configuré)', [
            'telephone' => substr($telephone, 0, 4).str_repeat('*', max(0, strlen($telephone) - 6)).substr($telephone, -2),
            'message' => app()->environment('local') ? $message : '[masqué]',
        ]);
    }
}
