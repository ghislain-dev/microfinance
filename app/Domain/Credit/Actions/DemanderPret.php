<?php

declare(strict_types=1);

namespace App\Domain\Credit\Actions;

use App\Domain\Credit\CreditRefuse;
use App\Domain\Credit\MethodeCalcul;
use App\Domain\Credit\Models\GarantiePret;
use App\Domain\Credit\Models\Pret;
use App\Domain\Credit\Models\ProduitCredit;
use App\Domain\Credit\Services\EligibiliteCredit;
use App\Domain\Credit\StatutPret;
use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Membres\Models\Membre;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use App\Support\Montant\Montant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Demande de prêt saisie par l'agent de crédit (ou le gérant) : conditions du produit vérifiées puis figées
 * sur le prêt, garanties enregistrées. Un seul prêt à la fois par membre (garanti par la base).
 */
final class DemanderPret
{
    public function __construct(private readonly EligibiliteCredit $eligibilite) {}

    /**
     * @param  list<array<string, mixed>>  $garanties  [type: caution, membre_id, montant] ou [type: bien, description, valeur?]
     */
    public function executer(User $agent, Membre $membre, ProduitCredit $produit, CompteEpargne $compte, Montant $montant,
        int $nombreEcheances, MethodeCalcul $methode, string $objet, array $garanties = []): Pret
    {
        AutoriserDansOrganisation::autoriser($agent, 'create', [Pret::class, $membre, $produit]);
        $objet = trim($objet);
        if ($objet === '' || mb_strlen($objet) > 500) {
            throw new CreditRefuse('Objet du prêt obligatoire (500 caractères au plus).');
        }
        if (! $produit->actif) {
            throw new CreditRefuse("Le produit {$produit->code} n'est plus proposé.");
        }
        $this->exigerConditions($membre, $produit, $compte, $montant, $nombreEcheances);
        $lignes = array_map(fn (array $g) => $this->garantie($g, $membre, $produit), $garanties);

        try {
            return DB::transaction(function () use ($agent, $membre, $produit, $compte, $montant, $nombreEcheances, $methode, $objet, $lignes) {
                // Verrou sur le membre : deux demandes simultanées ne prennent pas le même rang.
                $membre = Membre::query()->whereKey($membre->id)->lockForUpdate()->firstOrFail();
                if (Pret::query()->where('membre_id', $membre->id)->whereNotIn('statut', [StatutPret::Rejete->value, StatutPret::Solde->value])->exists()) {
                    throw new CreditRefuse("{$membre->nomComplet()} a déjà une demande ou un prêt en cours.");
                }
                $rang = (int) Pret::query()->where('membre_id', $membre->id)->max('rang') + 1;

                $pret = new Pret;
                $pret->forceFill([
                    'organisation_id' => $membre->organisation_id,
                    'membre_id' => $membre->id,
                    'produit_credit_id' => $produit->id,
                    'compte_epargne_id' => $compte->id,
                    'numero' => "{$membre->numero}-CR{$rang}",
                    'rang' => $rang,
                    'devise' => $produit->devise, // avant le montant : MontantCast lit la devise
                    'montant' => $montant,
                    'taux_mensuel_pb' => $produit->taux_mensuel_pb,
                    'periodicite' => $produit->periodicite,
                    'nombre_echeances' => $nombreEcheances,
                    'methode' => $methode,
                    'garantie_epargne_pb' => $produit->garantie_epargne_pb,
                    'penalite_jour_pb' => $produit->penalite_jour_pb,
                    'delai_grace_jours' => $produit->delai_grace_jours,
                    'objet' => $objet,
                    'statut' => StatutPret::EnAnalyse,
                    'saisi_par_id' => $agent->id,
                ])->save();

                foreach ($lignes as $ligne) {
                    (new GarantiePret)->forceFill(['organisation_id' => $pret->organisation_id, 'pret_id' => $pret->id, ...$ligne])->save();
                }

                return $pret;
            });
        } catch (UniqueConstraintViolationException) {
            throw new CreditRefuse("{$membre->nomComplet()} a déjà une demande ou un prêt en cours.");
        }
    }

    private function exigerConditions(Membre $membre, ProduitCredit $produit, CompteEpargne $compte, Montant $montant, int $nombre): void
    {
        $this->eligibilite->exigerMembreEtCompte($membre, $compte, $produit);
        if ($montant->devise !== $produit->devise
            || $montant->comparer($produit->montant_minimum) < 0 || $montant->comparer($produit->montant_maximum) > 0) {
            throw new CreditRefuse("Montant attendu : de {$produit->montant_minimum->formate()} à {$produit->montant_maximum->formate()}.");
        }
        if ($nombre < 1 || $nombre > $produit->duree_max) {
            throw new CreditRefuse("Nombre d'échéances : de 1 à {$produit->duree_max}.");
        }
        $inscrit = $membre->created_at;
        if ($produit->anciennete_mois > 0 && ($inscrit === null || $inscrit->copy()->addMonthsNoOverflow($produit->anciennete_mois)->isAfter(now()))) {
            throw new CreditRefuse("Ancienneté minimale : {$produit->anciennete_mois} mois depuis l'inscription.");
        }
        $this->eligibilite->exigerPlafond($membre, $produit, $montant);
    }

    /**
     * @param  array<string, mixed>  $saisie
     * @return array<string, mixed>
     */
    private function garantie(array $saisie, Membre $emprunteur, ProduitCredit $produit): array
    {
        $devise = $produit->devise;

        return match ($saisie['type'] ?? null) {
            'caution' => (function () use ($saisie, $emprunteur, $devise) {
                // Scope de l'organisation : un membre d'une autre organisation est introuvable.
                $caution = Membre::query()->find((int) ($saisie['membre_id'] ?? 0))
                    ?? throw new CreditRefuse('Caution introuvable dans votre organisation.');
                if ($caution->id === $emprunteur->id) {
                    throw new CreditRefuse("L'emprunteur ne peut pas être sa propre caution.");
                }
                $montant = Montant::depuisSaisie((string) ($saisie['montant'] ?? ''), $devise);
                if (! $montant->estPositif()) {
                    throw new CreditRefuse('Montant cautionné obligatoire.');
                }

                return ['type' => 'caution', 'membre_id' => $caution->id, 'devise' => $devise, 'montant' => $montant];
            })(),
            'bien' => (function () use ($saisie, $devise) {
                $description = trim((string) ($saisie['description'] ?? ''));
                if ($description === '' || mb_strlen($description) > 500) {
                    throw new CreditRefuse('Description du bien obligatoire (500 caractères au plus).');
                }
                $valeur = trim((string) ($saisie['valeur'] ?? ''));
                $ligne = ['type' => 'bien', 'description' => $description, 'devise' => $devise, 'piece_chemin' => $saisie['piece_chemin'] ?? null];

                // Valeur estimée facultative (MontantCast n'accepte qu'un Montant).
                return $valeur === '' ? $ligne : [...$ligne, 'montant' => Montant::depuisSaisie($valeur, $devise)];
            })(),
            default => throw new CreditRefuse('Type de garantie inconnu.'),
        };
    }
}
