<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Epargne\Actions\EnregistrerTypeCompte;
use App\Domain\Epargne\Models\TypeCompteEpargne;
use App\Domain\Membres\Actions\InscrireMembre;
use App\Domain\Membres\Models\Membre;
use App\Models\User;
use App\Support\Montant\Devise;

/**
 * Données d'épargne prêtes à l'emploi (sur un Guichet).
 */
final class Epargnant
{
    private static int $n = 0;

    public static function membre(Guichet $g): Membre
    {
        self::$n++;

        return app(InscrireMembre::class)->executer($g->gerant, $g->agence, [
            'nom' => 'Membre '.self::$n, 'sexe' => 'F', 'date_naissance' => '1990-01-01',
            'telephone' => '0850'.str_pad((string) self::$n, 6, '0', STR_PAD_LEFT),
            'piece_type' => 'Carte d’électeur', 'piece_numero' => 'EP-'.self::$n,
        ]);
    }

    /**
     * @param  array<string, mixed>  $parametres
     */
    public static function type(User $acteur, string $code = 'EPV', Devise $devise = Devise::USD, array $parametres = []): TypeCompteEpargne
    {
        return app(EnregistrerTypeCompte::class)->creer($acteur, [
            'code' => $code, 'libelle' => "Épargne {$code}", 'devise' => $devise->value,
            'solde_minimum' => '0', 'depot_minimum' => '0', 'frais_retrait' => '0', 'duree_blocage_mois' => null,
            ...$parametres,
        ]);
    }
}
