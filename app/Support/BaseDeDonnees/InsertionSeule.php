<?php

declare(strict_types=1);

namespace App\Support\BaseDeDonnees;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Triggers posés par les migrations : UPDATE et DELETE refusés par la base (MySQL et SQLite).
 */
final class InsertionSeule
{
    public static function proteger(string $table): void
    {
        foreach (['UPDATE', 'DELETE'] as $ordre) {
            self::trigger("{$table}_pas_de_".strtolower($ordre), "BEFORE {$ordre}", $table, "{$table} : insertion seule");
        }
    }

    public static function retirer(string $table): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS {$table}_pas_de_update");
        DB::unprepared("DROP TRIGGER IF EXISTS {$table}_pas_de_delete");
    }

    /**
     * Trigger qui refuse l'ordre avec un message, éventuellement sous condition (expression SQL sur NEW/OLD).
     * Les arguments viennent uniquement du code des migrations, jamais d'une saisie.
     */
    public static function trigger(string $nom, string $moment, string $table, string $message, ?string $condition = null): void
    {
        match (DB::getDriverName()) {
            'mysql' => DB::unprepared("CREATE TRIGGER {$nom} {$moment} ON {$table} FOR EACH ROW "
                .($condition === null ? '' : "IF {$condition} THEN ")
                ."SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'"
                .($condition === null ? '' : '; END IF')),
            'sqlite' => DB::unprepared("CREATE TRIGGER {$nom} {$moment} ON {$table} "
                .($condition === null ? '' : "WHEN {$condition} ")
                ."BEGIN SELECT RAISE(ABORT, '{$message}'); END"),
            default => throw new RuntimeException('Base non prise en charge : '.DB::getDriverName()),
        };
    }
}
