<?php

declare(strict_types=1);

use App\Domain\Organisation\Acces\Droit;
use App\Http\Controllers\Web\Auth\CodeOtpController;
use App\Http\Controllers\Web\Auth\ConnexionController;
use App\Http\Controllers\Web\MembreController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [ConnexionController::class, 'create'])->name('login');
    Route::post('/connexion', [ConnexionController::class, 'store'])->middleware('throttle:connexion');

    Route::get('/connexion/code', [CodeOtpController::class, 'create'])->name('connexion.code');
    Route::post('/connexion/code', [CodeOtpController::class, 'store'])->middleware('throttle:otp');
    Route::post('/connexion/code/renvoyer', [CodeOtpController::class, 'renvoyer'])
        ->middleware('throttle:otp')->name('connexion.code.renvoyer');
});

Route::middleware('auth')->group(function () {
    Route::post('/deconnexion', [ConnexionController::class, 'destroy'])->name('deconnexion');

    Route::redirect('/', '/tableau-de-bord');
    Route::view('/tableau-de-bord', 'tableau-de-bord')
        ->middleware('can:'.Droit::TableauDeBordVoir->value)->name('tableau-de-bord');

    Route::get('/membres', [MembreController::class, 'index'])->name('membres.index');
    Route::get('/membres/creer', [MembreController::class, 'create'])->name('membres.create');
    Route::post('/membres', [MembreController::class, 'store'])->name('membres.store');
    Route::get('/membres/{membre}', [MembreController::class, 'show'])->whereNumber('membre')->name('membres.show');
    Route::get('/membres/{membre}/modifier', [MembreController::class, 'edit'])->whereNumber('membre')->name('membres.edit');
    Route::put('/membres/{membre}', [MembreController::class, 'update'])->whereNumber('membre')->name('membres.update');
    Route::patch('/membres/{membre}/statut', [MembreController::class, 'changerStatut'])->whereNumber('membre')->name('membres.statut');
});
