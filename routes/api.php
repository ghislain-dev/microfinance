<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\AppareilController;
use App\Http\Controllers\Api\V1\Auth\ConnexionMembreController;
use App\Http\Controllers\Api\V1\Auth\JetonController;
use App\Http\Controllers\Api\V1\Auth\MotDePasseController;
use App\Http\Controllers\Api\V1\Collecte\SynchronisationController;
use App\Http\Controllers\Api\V1\Collecte\TourneeController;
use App\Http\Controllers\Api\V1\Membre\EspaceMembreController;
use App\Http\Middleware\AppareilAutorise;
use App\Http\Middleware\MotDePasseAChanger;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/jetons', [JetonController::class, 'store'])->middleware('throttle:connexion');
    Route::post('/auth/membre', [ConnexionMembreController::class, 'store'])->middleware('throttle:connexion-membre');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/deconnexion', [JetonController::class, 'destroy']);
        Route::get('/moi', [AppareilController::class, 'moi']);
        Route::get('/appareils', [AppareilController::class, 'index']);
        Route::delete('/appareils/{id}', [AppareilController::class, 'destroy'])->whereNumber('id');
        Route::post('/moi/mot-de-passe', [MotDePasseController::class, 'update']);

        // Espace membre : jeton « membre », mot de passe provisoire déjà changé.
        Route::middleware(['abilities:membre', MotDePasseAChanger::class])->prefix('membre')->group(function () {
            Route::get('/comptes', [EspaceMembreController::class, 'comptes']);
            Route::get('/comptes/{compte}/mouvements', [EspaceMembreController::class, 'mouvements'])->whereNumber('compte');
            Route::get('/prets', [EspaceMembreController::class, 'prets']);
            Route::get('/tontines', [EspaceMembreController::class, 'tontines']);
        });

        // Collecte : jeton « collecteur » d'un téléphone autorisé.
        Route::middleware(['abilities:collecteur', AppareilAutorise::class])->prefix('collecte')->group(function () {
            Route::get('/tournee', [TourneeController::class, 'show']);
        });
        Route::post('/sync/lots', [SynchronisationController::class, 'store'])->middleware(['abilities:collecteur', AppareilAutorise::class]);
    });
});
