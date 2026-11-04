<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

// Contrôle de nuit des soldes (CLAUDE.md §4.4), à 2 h, une seule instance à la fois.
Schedule::command('finance:verifier-soldes')->dailyAt('02:00')->withoutOverlapping()->onOneServer();

// Intérêts d'épargne du mois écoulé, le 1er à 3 h : lots à valider avant tout versement.
Schedule::command('epargne:calculer-interets')->monthlyOn(1, '03:00')->withoutOverlapping()->onOneServer();

// Pénalités de retard des prêts, chaque nuit à 1 h (avant le contrôle des soldes).
Schedule::command('credit:constater-penalites')->dailyAt('01:00')->withoutOverlapping()->onOneServer();

// Rappels push aux membres (échéances de prêt, veille du tour de tontine), chaque jour à 8 h.
Schedule::command('notifications:rappels')->dailyAt('08:00')->withoutOverlapping()->onOneServer();
