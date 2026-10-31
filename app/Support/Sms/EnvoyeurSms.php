<?php

declare(strict_types=1);

namespace App\Support\Sms;

/**
 * Fournisseur d'envoi de SMS. Brancher ici le fournisseur choisi (Africa's Talking, Twilio, opérateur local…).
 */
interface EnvoyeurSms
{
    public function envoyer(string $telephone, string $message): void;
}
