<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/Support/fonctions_tontine.php';

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Concurrence : de vrais processus parallèles doivent voir les données, donc pas de transaction de test.
pest()->extend(TestCase::class)
    ->use(DatabaseMigrations::class)
    ->in('Concurrence');
