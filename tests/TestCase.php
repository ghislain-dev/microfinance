<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Vérifie la base avant que RefreshDatabase ne la vide.
     *
     * @return array<string, mixed>
     */
    protected function setUpTraits(): array
    {
        $connexion = (string) config('database.default');

        self::verifierBaseDeTest(
            (string) config("database.connections.{$connexion}.driver"),
            (string) config("database.connections.{$connexion}.database"),
        );

        return parent::setUpTraits();
    }

    /**
     * Les tests vident la base : seules SQLite en mémoire et une base « *_test » sont acceptées.
     */
    public static function verifierBaseDeTest(string $driver, string $base): void
    {
        if ($driver === 'sqlite' && $base === ':memory:') {
            return;
        }

        if ($driver !== 'sqlite' && str_ends_with($base, '_test')) {
            return;
        }

        throw new RuntimeException("Tests refusés sur la base « {$base} » ({$driver}) : ce n'est pas une base de test.");
    }
}
