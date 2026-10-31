<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Finance\Actions\OuvrirCompte;
use App\Domain\Finance\Comptes\NatureCompte;
use App\Domain\Finance\Models\Compte;
use App\Models\User;
use App\Support\Montant\Devise;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Données brutes pour tester les protections en base (hors moteur, volontairement).
 */
final class GrandLivre
{
    public static function compte(User $utilisateur, NatureCompte $nature = NatureCompte::Caisse, Devise $devise = Devise::USD): Compte
    {
        auth()->setUser($utilisateur);

        return (new OuvrirCompte)->executer(Str::random(8), $nature->libelle(), $nature, $devise);
    }

    public static function operationBrute(User $auteur, ?string $cle = null): int
    {
        return DB::table('operations')->insertGetId([
            'organisation_id' => $auteur->organisation_id,
            'idempotency_key' => $cle ?? (string) Str::uuid(),
            'type' => 'essai',
            'devise' => 'USD',
            'auteur_id' => $auteur->id,
            'date_valeur' => now()->toDateString(),
            'created_at' => now(),
        ]);
    }

    public static function ecritureBrute(int $operationId, Compte $compte, int $debit, int $credit): void
    {
        DB::table('ecritures')->insert([
            'organisation_id' => $compte->organisation_id,
            'operation_id' => $operationId,
            'compte_id' => $compte->id,
            'debit' => $debit,
            'credit' => $credit,
            'devise' => $compte->devise->value,
            'created_at' => now(),
        ]);
    }
}
