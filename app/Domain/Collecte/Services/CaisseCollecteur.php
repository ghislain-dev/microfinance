<?php

declare(strict_types=1);

namespace App\Domain\Collecte\Services;

use App\Domain\Caisse\Models\Caisse;
use App\Domain\Finance\Actions\OuvrirCompte;
use App\Domain\Finance\Comptes\NatureCompte;
use App\Domain\Membres\Models\Membre;
use App\Models\User;
use App\Support\Montant\Devise;

/**
 * Caisse personnelle du collecteur : ses espèces entre la collecte et le versement au guichet.
 */
final class CaisseCollecteur
{
    public function __construct(private readonly OuvrirCompte $ouvrirCompte) {}

    public function de(User $collecteur): ?Caisse
    {
        return Caisse::query()->where('collecteur_id', $collecteur->id)->first();
    }

    /**
     * Ouverte à la première affectation, dans l'agence du premier membre affecté.
     */
    public function obtenir(User $collecteur, Membre $membre): Caisse
    {
        $existante = $this->de($collecteur);
        if ($existante !== null) {
            return $existante;
        }

        $membre->loadMissing('agence');
        $code = "COL-{$collecteur->id}";
        $caisse = new Caisse(['code' => $code, 'libelle' => "Collecteur {$collecteur->name}"]);
        $caisse->forceFill(['organisation_id' => $collecteur->organisation_id, 'agence_id' => $membre->agence->id, 'collecteur_id' => $collecteur->id])->save();
        foreach (Devise::cases() as $devise) {
            $compte = $this->ouvrirCompte->executer("CAISSE-{$code}-{$devise->value}", "Caisse collecteur {$collecteur->name} ({$devise->value})",
                NatureCompte::Caisse, $devise, $membre->agence);
            $caisse->comptes()->attach($compte, ['devise' => $devise->value, 'organisation_id' => $collecteur->organisation_id]);
        }

        return $caisse;
    }
}
