<?php

declare(strict_types=1);

namespace App\Domain\Organisation\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Envoi du code de connexion, en file d'attente ; le contenu de la file est chiffré (il porte le code).
 */
class CodeOtpNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $code) {}

    /**
     * @return list<string>
     */
    public function via(User $destinataire): array
    {
        return [(string) config('otp.canal')];
    }

    public function toMail(User $destinataire): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre code de connexion')
            ->greeting("Bonjour {$destinataire->name},")
            ->line("Votre code de connexion est : **{$this->code}**")
            ->line('Il est valable '.config('otp.validite_minutes').' minutes et ne peut servir qu’une fois.')
            ->line('Si vous n’êtes pas à l’origine de cette demande, prévenez votre administrateur.');
    }

    public function toSms(User $destinataire): string
    {
        return "Code de connexion : {$this->code} (valable ".config('otp.validite_minutes').' min). Ne le communiquez à personne.';
    }
}
