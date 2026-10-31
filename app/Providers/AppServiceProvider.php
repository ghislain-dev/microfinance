<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\Web\Auth\ConnexionController;
use App\Models\User;
use App\Support\Sms\CanalSms;
use App\Support\Sms\EnvoyeurSms;
use App\Support\Sms\JournalSms;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Remplacer JournalSms par le fournisseur choisi le moment venu.
        $this->app->bind(EnvoyeurSms::class, JournalSms::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Hors production : pas de chargement paresseux (N+1), pas d'attribut ignoré ou inexistant.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Rôles et permissions évalués dans l'organisation de l'utilisateur connecté (spatie « teams »).
        Event::listen(Authenticated::class, function (Authenticated $evenement) {
            setPermissionsTeamId($evenement->user->getAttribute('organisation_id'));
        });

        // Un jeton n'est valable que pour un compte et une organisation actifs.
        Sanctum::authenticateAccessTokensUsing(function (PersonalAccessToken $jeton, bool $valide): bool {
            $utilisateur = $jeton->tokenable;
            if (! $valide || ! $utilisateur instanceof User || ! $utilisateur->actif || ! $utilisateur->organisation?->actif) {
                return false;
            }
            setPermissionsTeamId($utilisateur->organisation_id);

            return true;
        });

        Notification::extend('sms', fn (Application $app) => new CanalSms($app->make(EnvoyeurSms::class)));

        $this->limiterLesRequetes();

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }

    /**
     * references/securite.md : 5 tentatives par minute sur la connexion et l'OTP, limite sur toute l'API.
     */
    private function limiterLesRequetes(): void
    {
        RateLimiter::for('connexion', fn (Request $requete) => Limit::perMinute(5)
            ->by(Str::lower($requete->string('email')->value()).'|'.$requete->ip()));

        // Clé = utilisateur en attente du code (pas l'id de session, qui change si le cookie est refusé).
        RateLimiter::for('otp', fn (Request $requete) => Limit::perMinute(5)
            ->by($requete->session()->get(ConnexionController::EN_ATTENTE, '-').'|'.$requete->ip()));

        RateLimiter::for('api', fn (Request $requete) => Limit::perMinute(60)
            ->by((string) ($requete->user()?->getAuthIdentifier() ?? $requete->ip())));
    }
}
