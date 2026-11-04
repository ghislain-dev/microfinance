<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Jobs;

use App\Domain\Notifications\Models\JetonPush;
use App\Domain\Notifications\Models\NotificationPush;
use App\Support\Push\EnvoyeurPush;
use App\Support\Push\ResultatPush;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/**
 * Envoi d'une notification à tous les téléphones du membre, en file d'attente (CLAUDE.md §6).
 */
final class EnvoyerPush implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 120, 600, 1800];

    public function __construct(public readonly int $notificationId) {}

    public function handle(EnvoyeurPush $envoyeur): void
    {
        $notification = NotificationPush::withoutGlobalScopes()->find($this->notificationId);
        if ($notification === null || $notification->envoye_le !== null) {
            return;
        }

        $echec = false;
        $jetons = JetonPush::withoutGlobalScopes()->where('user_id', $notification->getAttribute('user_id'))->get();
        foreach ($jetons as $jeton) {
            $resultat = $envoyeur->envoyer((string) $jeton->getAttribute('jeton'), $notification->titre, $notification->corps, $notification->donnees);
            match ($resultat) {
                ResultatPush::JetonInvalide => $jeton->delete(),
                ResultatPush::Echec => $echec = true,
                ResultatPush::Envoye => null,
            };
        }
        if ($echec) {
            throw new RuntimeException('Envoi push à réessayer.'); // la file d'attente réessaie
        }

        $notification->forceFill(['envoye_le' => now()])->save();
    }
}
