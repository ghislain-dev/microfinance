<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
