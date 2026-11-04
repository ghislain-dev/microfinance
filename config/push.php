<?php

declare(strict_types=1);

return [
    // journal (par défaut, n'envoie rien) | firebase
    'pilote' => env('PUSH_PILOTE', 'journal'),

    'firebase' => [
        'projet' => env('FIREBASE_PROJET'),
        // Fichier JSON du compte de service, hors du dépôt et hors de public/ (jamais commité).
        'compte_service' => env('FIREBASE_COMPTE_SERVICE'),
    ],
];
