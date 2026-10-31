<?php

declare(strict_types=1);

namespace App\Support\Sms;

use App\Models\User;
use Illuminate\Notifications\Notification;
use LogicException;

/**
 * Canal de notification « sms » : la notification fournit toSms(), l'envoyeur fait l'envoi.
 */
final class CanalSms
{
    public function __construct(private readonly EnvoyeurSms $envoyeur) {}

    public function send(User $destinataire, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            throw new LogicException($notification::class.' ne sait pas s’envoyer par SMS.');
        }

        if ($destinataire->telephone === null) {
            throw new LogicException('Aucun numéro de téléphone pour l’utilisateur '.$destinataire->id);
        }

        $this->envoyeur->envoyer($destinataire->telephone, (string) $notification->toSms($destinataire));
    }
}
