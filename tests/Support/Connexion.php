<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Organisation\Notifications\CodeOtpNotification;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

/**
 * Lecture du dernier code OTP envoyé (avec Notification::fake()).
 */
final class Connexion
{
    public static function dernierCode(User $utilisateur): string
    {
        $code = null;
        Notification::assertSentTo($utilisateur, CodeOtpNotification::class, function (CodeOtpNotification $n) use (&$code) {
            $code = $n->code;

            return true;
        });

        return $code ?? throw new RuntimeException('Aucun code envoyé');
    }
}
