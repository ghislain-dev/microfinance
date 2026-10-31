<?php

declare(strict_types=1);

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Comptes\NatureCompte;
use App\Domain\Finance\Models\Compte;
use App\Domain\Organisation\Models\Agence;
use App\Support\Montant\Devise;
use App\Support\MultiOrganisation\OrganisationIncoherente;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Ouvre un compte à solde nul dans l'organisation de l'utilisateur connecté.
 */
final class OuvrirCompte
{
    public function executer(
        string $numero,
        string $libelle,
        NatureCompte $nature,
        Devise $devise,
        ?Agence $agence = null,
        ?string $syscohada = null,
    ): Compte {
        $compte = new Compte([
            'numero' => $numero,
            'libelle' => $libelle,
            'nature' => $nature,
            'devise' => $devise,
            'syscohada' => $syscohada,
        ]);

        if ($agence !== null) {
            if (auth()->user()?->getAttribute('organisation_id') !== $agence->organisation_id) {
                throw new OrganisationIncoherente('L’agence appartient à une autre organisation.');
            }
            $compte->agence_id = $agence->id;
        }

        $compte->save();

        return $compte->refresh();
    }

    /**
     * Compte interne identifié par son numéro (ex. produits des excédents de caisse) : ouvert au premier
     * besoin, dans l'organisation de l'utilisateur connecté.
     */
    public function obtenir(string $numero, string $libelle, NatureCompte $nature, Devise $devise): Compte
    {
        $existant = Compte::query()->where('numero', $numero)->first();
        if ($existant !== null) {
            return $existant;
        }

        try {
            return $this->executer($numero, $libelle, $nature, $devise);
        } catch (UniqueConstraintViolationException) {
            return Compte::query()->where('numero', $numero)->firstOrFail(); // ouvert au même instant
        }
    }
}
