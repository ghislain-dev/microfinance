<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Support\Audit\JournalAudit;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Événements journalisés hors des modèles métier (qui portent le trait EstAudite).
 */
class AuditServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(Login::class, function (Login $evenement) {
            $this->journal()->enregistrer('connexion', $this->utilisateur($evenement->user), acteur: $this->utilisateur($evenement->user));
        });

        Event::listen(Logout::class, function (Logout $evenement) {
            $this->journal()->enregistrer('deconnexion', acteur: $this->utilisateur($evenement->user));
        });

        Event::listen(Failed::class, function (Failed $evenement) {
            $this->journal()->enregistrer(
                'connexion.echec',
                $this->utilisateur($evenement->user),
                apres: ['email' => $evenement->credentials['email'] ?? null],
                acteur: $this->utilisateur($evenement->user),
            );
        });

        Event::listen('eloquent.created: '.PersonalAccessToken::class, function (PersonalAccessToken $jeton) {
            $this->journal()->enregistrer('jeton.creation', $jeton, apres: ['appareil' => $jeton->name], acteur: $this->utilisateur($jeton->tokenable));
        });

        Event::listen('eloquent.deleted: '.PersonalAccessToken::class, function (PersonalAccessToken $jeton) {
            $this->journal()->enregistrer('jeton.revocation', $jeton, avant: ['appareil' => $jeton->name], acteur: $this->utilisateur($jeton->tokenable));
        });
    }

    private function journal(): JournalAudit
    {
        return $this->app->make(JournalAudit::class);
    }

    private function utilisateur(mixed $utilisateur): ?User
    {
        return $utilisateur instanceof User ? $utilisateur : null;
    }
}
