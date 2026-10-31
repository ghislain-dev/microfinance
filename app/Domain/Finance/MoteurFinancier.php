<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use App\Domain\Finance\Comptes\Sens;
use App\Domain\Finance\Events\OperationEnregistree;
use App\Domain\Finance\Exceptions\CompteIndisponible;
use App\Domain\Finance\Exceptions\OperationDesequilibree;
use App\Domain\Finance\Exceptions\PeriodeCloturee;
use App\Domain\Finance\Exceptions\SoldeInsuffisant;
use App\Domain\Finance\Models\ClotureComptable;
use App\Domain\Finance\Models\Compte;
use App\Domain\Finance\Models\Ecriture;
use App\Domain\Finance\Models\Operation;
use App\Models\User;
use App\Support\Audit\JournalAudit;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Seul point d'écriture de l'argent (CLAUDE.md §4.2) : une transaction, verrous sur les comptes
 * triés par id, partie double équilibrée, clé d'idempotence, période ouverte, pas de découvert interdit.
 */
final class MoteurFinancier
{
    public function __construct(private readonly JournalAudit $journal) {}

    /**
     * Rejouer la même clé renvoie l'opération déjà enregistrée, sans rien créer.
     *
     * @param  list<LigneEcriture>  $lignes
     */
    public function executer(
        string $cle,
        string $type,
        array $lignes,
        ?CarbonInterface $dateValeur = null,
        ?string $motif = null,
        ?Operation $extourneDe = null,
        ?User $validateur = null,
    ): Operation {
        if (($deja = $this->parCle($cle)) !== null) {
            return $deja;
        }

        $devise = $this->verifierEquilibre($lignes);
        $dateValeur = CarbonImmutable::parse(($dateValeur ?? today())->toDateString());

        try {
            return DB::transaction(function () use ($cle, $type, $lignes, $devise, $dateValeur, $motif, $extourneDe, $validateur) {
                $ids = collect($lignes)->pluck('compteId')->unique()->sort()->values();
                // Ordre fixe des verrous : pas d'interblocage entre deux opérations concurrentes.
                $comptes = Compte::query()->whereKey($ids->all())->orderBy('id')->lockForUpdate()->get()->keyBy('id');

                $organisationId = $this->verifierComptes($comptes, $ids->all(), $devise);
                $this->verifierPeriodeOuverte($organisationId, $dateValeur);
                $mouvements = $this->mouvements($comptes, $lignes);
                $this->verifierDecouvert($comptes, $mouvements);

                $operation = new Operation([
                    'idempotency_key' => $cle,
                    'type' => $type,
                    'devise' => $devise,
                    'auteur_id' => Auth::hasUser() ? Auth::id() : null,
                    'valide_par_id' => $validateur?->id,
                    'extourne_de_id' => $extourneDe?->id,
                    'date_valeur' => $dateValeur->toDateString(),
                    'motif' => $motif,
                ]);
                $operation->organisation_id = $organisationId;
                $operation->save();

                foreach ($lignes as $ligne) {
                    $ecriture = new Ecriture([
                        'compte_id' => $ligne->compteId,
                        'debit' => $ligne->debit,
                        'credit' => $ligne->credit,
                        'devise' => $ligne->devise,
                    ]);
                    $ecriture->organisation_id = $organisationId;
                    $ecriture->operation_id = $operation->id;
                    $ecriture->save();
                }

                foreach ($mouvements as $compteId => $mouvement) {
                    $compte = $comptes[$compteId];
                    $compte->solde_cache = $compte->solde_cache->plus($mouvement);
                    $compte->save();
                }

                $this->journal->enregistrer('operations.creation', $operation, apres: ['type' => $type, 'cle' => $cle]);
                OperationEnregistree::dispatch($operation);

                return $operation;
            }, attempts: 3); // rejoue si MySQL signale un interblocage
        } catch (UniqueConstraintViolationException $e) {
            // Même clé envoyée en même temps : l'autre requête a gagné, on renvoie son résultat.
            return $this->parCle($cle) ?? throw $e;
        }
    }

    /**
     * Solde réel (sens normal de la nature) : la somme des écritures, qui seule fait foi.
     */
    public function soldeReel(Compte $compte): Montant
    {
        $totaux = Ecriture::query()->where('compte_id', $compte->id)
            ->selectRaw('COALESCE(SUM(debit), 0) AS debits, COALESCE(SUM(credit), 0) AS credits')
            ->toBase()->first();
        $debits = (int) ($totaux->debits ?? 0);
        $credits = (int) ($totaux->credits ?? 0);

        return new Montant($compte->nature->sens() === Sens::Debit ? $debits - $credits : $credits - $debits, $compte->devise);
    }

    private function parCle(string $cle): ?Operation
    {
        return Operation::query()->where('idempotency_key', $cle)->first();
    }

    /**
     * @param  list<LigneEcriture>  $lignes
     */
    private function verifierEquilibre(array $lignes): Devise
    {
        if (count($lignes) < 2) {
            throw new OperationDesequilibree('Une opération compte au moins deux lignes.');
        }

        $devise = $lignes[0]->devise;
        $debits = $credits = Montant::zero($devise);
        foreach ($lignes as $ligne) {
            if ($ligne->devise !== $devise) {
                throw new OperationDesequilibree('Une opération = une devise (conversion : deux opérations liées).');
            }
            $debits = $debits->plus(new Montant($ligne->debit, $devise));
            $credits = $credits->plus(new Montant($ligne->credit, $devise));
        }

        if (! $debits->egal($credits)) {
            throw new OperationDesequilibree("Débits {$debits->formate()} ≠ crédits {$credits->formate()}.");
        }

        return $devise;
    }

    /**
     * @param  Collection<int, Compte>  $comptes
     * @param  list<int>  $ids
     */
    private function verifierComptes(Collection $comptes, array $ids, Devise $devise): int
    {
        foreach ($ids as $id) {
            $compte = $comptes->get($id);
            if ($compte === null) {
                throw new CompteIndisponible("Compte {$id} introuvable dans votre organisation.");
            }
            if (! $compte->actif) {
                throw new CompteIndisponible("Compte {$compte->numero} inactif.");
            }
            if ($compte->devise !== $devise) {
                throw new CompteIndisponible("Compte {$compte->numero} en {$compte->devise->value}, opération en {$devise->value}.");
            }
        }

        $organisations = $comptes->pluck('organisation_id')->unique();
        if ($organisations->count() !== 1) {
            throw new CompteIndisponible('Tous les comptes d’une opération appartiennent à la même organisation.');
        }

        return (int) $organisations->first();
    }

    private function verifierPeriodeOuverte(int $organisationId, CarbonImmutable $dateValeur): void
    {
        $cloture = ClotureComptable::withoutGlobalScopes()->where('organisation_id', $organisationId)->max('date_fin');

        if ($cloture !== null && $dateValeur->toDateString() <= (string) $cloture) {
            throw new PeriodeCloturee("Période clôturée jusqu'au {$cloture} : date de valeur {$dateValeur->toDateString()} refusée.");
        }
    }

    /**
     * Variation du solde de chaque compte, dans le sens normal de sa nature.
     *
     * @param  Collection<int, Compte>  $comptes
     * @param  list<LigneEcriture>  $lignes
     * @return array<int, Montant>
     */
    private function mouvements(Collection $comptes, array $lignes): array
    {
        $mouvements = [];
        foreach ($lignes as $ligne) {
            $compte = $comptes[$ligne->compteId];
            $variation = new Montant(
                $compte->nature->sens() === Sens::Debit ? $ligne->debit - $ligne->credit : $ligne->credit - $ligne->debit,
                $compte->devise,
            );
            $mouvements[$compte->id] = isset($mouvements[$compte->id]) ? $mouvements[$compte->id]->plus($variation) : $variation;
        }

        return $mouvements;
    }

    /**
     * @param  Collection<int, Compte>  $comptes
     * @param  array<int, Montant>  $mouvements
     */
    private function verifierDecouvert(Collection $comptes, array $mouvements): void
    {
        foreach ($mouvements as $compteId => $mouvement) {
            $compte = $comptes[$compteId];
            if (! $mouvement->estNegatif() || $compte->nature->peutEtreNegatif()) {
                continue;
            }

            $apres = $this->soldeReel($compte)->plus($mouvement);
            if ($apres->estNegatif()) {
                throw new SoldeInsuffisant("Solde insuffisant sur {$compte->numero} : il deviendrait {$apres->formate()}.");
            }
        }
    }
}
