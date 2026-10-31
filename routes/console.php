<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

// Contrôle de nuit des soldes (CLAUDE.md §4.4), à 2 h, une seule instance à la fois.
Schedule::command('finance:verifier-soldes')->dailyAt('02:00')->withoutOverlapping()->onOneServer();
