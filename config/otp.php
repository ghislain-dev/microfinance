<?php

declare(strict_types=1);

// Code à usage unique demandé à chaque connexion (web et création de jeton mobile).
// Paramètres validés le 2026-10-31.
return [
    // « mail » tant qu'aucun fournisseur SMS n'est choisi ; « sms » est prêt (App\Support\Sms\EnvoyeurSms).
    'canal' => env('OTP_CANAL', 'mail'),
    'longueur' => 6,
    'validite_minutes' => 10,
    'essais_max' => 3,
    'delai_renvoi_secondes' => 60,
];
