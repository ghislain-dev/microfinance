<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Jobs\EnvoyerPush;
use App\Domain\Notifications\Models\NotificationPush;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Prépare une notification pour le membre (s'il a un accès mobile) et la met en file d'attente.
 * La clé empêche les doublons (rejeu d'une opération, commande relancée).
 */
final class NotifierMembre
{
    /**
     * @param  array<string, string>  $donnees
     */
    public function envoyer(int $organisationId, int $membreId, string $cle, string $titre, string $corps, array $donnees = []): void
    {
        $utilisateur = User::withoutGlobalScopes()->where('organisation_id', $organisationId)->where('membre_id', $membreId)->where('actif', true)->first();
        if ($utilisateur === null) {
            return;
        }

        $notification = new NotificationPush;
        try {
            $notification->forceFill([
                'organisation_id' => $organisationId, 'user_id' => $utilisateur->id, 'cle' => $cle,
                'titre' => mb_substr($titre, 0, 100), 'corps' => mb_substr($corps, 0, 500), 'donnees' => $donnees,
            ])->save();
        } catch (UniqueConstraintViolationException) {
            return; // déjà notifié
        }

        EnvoyerPush::dispatch($notification->id)->afterCommit();
    }
}
