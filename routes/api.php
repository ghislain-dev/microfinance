<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\AppareilController;
use App\Http\Controllers\Api\V1\Auth\JetonController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/jetons', [JetonController::class, 'store'])->middleware('throttle:connexion');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/deconnexion', [JetonController::class, 'destroy']);
        Route::get('/moi', [AppareilController::class, 'moi']);
        Route::get('/appareils', [AppareilController::class, 'index']);
        Route::delete('/appareils/{id}', [AppareilController::class, 'destroy'])->whereNumber('id');
    });
});
