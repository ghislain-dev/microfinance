<?php

declare(strict_types=1);

namespace App\Domain\Collecte\Services;

use App\Domain\Caisse\Models\Caisse;
use App\Domain\Collecte\CollecteRefusee;
use App\Domain\Collecte\Models\Appareil;
use App\Domain\Collecte\Models\LotSync;
use App\Domain\Collecte\Models\OperationSync;
use App\Domain\Collecte\Models\PlafondCollecte;
use App\Domain\Collecte\Models\Tournee;
use App\Domain\Credit\Actions\RembourserPret;
use App\Domain\Credit\CreditRefuse;
use App\Domain\Credit\Models\Pret;
use App\Domain\Credit\Models\Remboursement;
use App\Domain\Credit\ModeRemboursement;
use App\Domain\Epargne\Actions\Deposer;
use App\Domain\Epargne\Actions\Retirer;
use App\Domain\Epargne\Exceptions\CompteEpargneRefuse;
use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Epargne\Models\MouvementEpargne;
use App\Domain\Epargne\Services\RegleEpargne;
use App\Domain\Finance\Exceptions\RefusFinancier;
use App\Domain\Membres\Models\Membre;
use App\Domain\Tontines\Actions\EncaisserCotisationCollecte;
use App\Domain\Tontines\Models\Cycle;
use App\Domain\Tontines\TontineRefusee;
use App\Models\User;
use App\Support\Montant\Montant;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Réception d'un lot du téléphone (skill §8) : chaque opération est traitée seule, par le moteur, avec la clé
 * générée au téléphone ; réponse par opération cree | deja_traite | rejete. Le serveur fixe la date de valeur
 * et ne croit jamais un solde, un compte ou une devise envoyés : tout est relu en base.
 */
final class Synchronisation
{
    public function __construct(
        private readonly CaisseCollecteur $caisses,
        private readonly RegleEpargne $regles,
        private readonly Deposer $deposer,
        private readonly Retirer $retirer,
        private readonly RembourserPret $rembourser,
        private readonly EncaisserCotisationCollecte $cotiser,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $operations  validées par la requête (types et formats)
     * @return list<array<string, mixed>>
     */
    public function traiter(User $collecteur, Appareil $appareil, string $lotUuid, array $operations): array
    {
        $lot = new LotSync;
        $lot->forceFill(['organisation_id' => $collecteur->organisation_id, 'lot_uuid' => $lotUuid,
            'collecteur_id' => $collecteur->id, 'appareil_id' => $appareil->id])->save();

        $resultats = array_map(fn (array $op) => $this->operation($collecteur, $lot, $op), $operations);
        $appareil->forceFill(['derniere_sync_le' => now()])->save();

        return $resultats;
    }

    /**
     * @param  array<string, mixed>  $op
     * @return array<string, mixed>
     */
    private function operation(User $collecteur, LotSync $lot, array $op): array
    {
        $cle = (string) $op['cle'];

        try {
            return DB::transaction(function () use ($collecteur, $lot, $op, $cle) {
                // Verrou sur le collecteur : ses envois simultanés (même lot renvoyé) passent l'un après l'autre,
                // et le second trouve le résultat du premier.
                User::query()->whereKey($collecteur->id)->lockForUpdate()->firstOrFail();
                if (($deja = OperationSync::query()->where('cle', $cle)->first()) !== null) {
                    return $this->resultat($deja, rejoue: true);
                }

                return $this->resultat($this->journaliser($lot, $op, 'cree', null, $this->executer($collecteur, $op)), rejoue: false);
            });
        } catch (CollecteRefusee|CompteEpargneRefuse|CreditRefuse|TontineRefusee|RefusFinancier|AuthorizationException $e) {
            // Transaction annulée (aucun argent n'a bougé) ; le refus est consigné à part.
            try {
                return $this->resultat($this->journaliser($lot, $op, 'rejete', $e->getMessage(), null), rejoue: false);
            } catch (UniqueConstraintViolationException) {
                return $this->resultat(OperationSync::query()->where('cle', $cle)->firstOrFail(), rejoue: true);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $op
     */
    private function executer(User $collecteur, array $op): int
    {
        $membre = Membre::query()->find((int) ($op['membre_id'] ?? 0));
        $enTournee = $membre !== null && Tournee::query()->where('collecteur_id', $collecteur->id)
            ->where('membre_id', $membre->id)->whereNull('retire_le')->exists();
        if (! $enTournee) {
            throw new CollecteRefusee('Membre hors de votre tournée.');
        }
        $caisse = $this->caisses->de($collecteur) ?? throw new CollecteRefusee('Aucune caisse de collecte.');
        $centimes = (int) ($op['montant_centimes'] ?? 0);
        $cle = (string) $op['cle'];

        return match ($op['type']) {
            'depot' => $this->deposer->executer($collecteur, $this->compte($membre, $op), $this->montant($this->compte($membre, $op), $centimes), $cle, $caisse)->getAttribute('operation_id'),
            'retrait' => $this->retrait($collecteur, $this->compte($membre, $op), $centimes, $cle, $caisse),
            'remboursement' => $this->remboursement($collecteur, $membre, $op, $centimes, $cle, $caisse),
            'cotisation' => $this->cotisation($collecteur, $membre, $op, $centimes, $cle, $caisse),
            default => throw new CollecteRefusee("Type d'opération non pris en charge : {$op['type']}."),
        };
    }

    /**
     * @param  array<string, mixed>  $op
     */
    private function compte(Membre $membre, array $op): CompteEpargne
    {
        return CompteEpargne::query()->with(['type', 'compte', 'membre'])->where('membre_id', $membre->id)->whereKey((int) ($op['compte_epargne_id'] ?? 0))->first()
            ?? throw new CollecteRefusee("Compte d'épargne introuvable pour {$membre->numero}.");
    }

    private function montant(CompteEpargne $compte, int $centimes): Montant
    {
        if ($centimes <= 0) {
            throw new CollecteRefusee('Montant positif attendu.');
        }

        return new Montant($centimes, $compte->type->devise);
    }

    private function retrait(User $collecteur, CompteEpargne $compte, int $centimes, string $cle, Caisse $caisse): int
    {
        $montant = $this->montant($compte, $centimes);
        $plafond = PlafondCollecte::query()->where('devise', $montant->devise->value)->first()
            ?? throw new CollecteRefusee("Aucun plafond de retrait fixé en {$montant->devise->value}.");
        if ($montant->comparer($plafond->plafond_retrait) > 0) {
            throw new CollecteRefusee("Retrait au-delà du plafond hors-ligne ({$plafond->plafond_retrait->formate()}).");
        }
        if ($this->regles->depasseSeuil($montant)) {
            throw new CollecteRefusee('Retrait au-delà du seuil de validation : le membre passe au guichet.');
        }

        return (int) $this->retirer->executer($collecteur, $compte, $montant, $cle, caisseCollecteur: $caisse)->getAttribute('operation_id');
    }

    /**
     * @param  array<string, mixed>  $op
     */
    private function remboursement(User $collecteur, Membre $membre, array $op, int $centimes, string $cle, Caisse $caisse): int
    {
        $pret = Pret::query()->where('membre_id', $membre->id)->whereKey((int) ($op['pret_id'] ?? 0))->first()
            ?? throw new CollecteRefusee("Prêt introuvable pour {$membre->numero}.");
        if ($centimes <= 0) {
            throw new CollecteRefusee('Montant positif attendu.');
        }

        return (int) $this->rembourser->executer($collecteur, $pret, new Montant($centimes, $pret->devise), ModeRemboursement::Especes, $cle, $caisse)
            ->getAttribute('operation_id');
    }

    /**
     * @param  array<string, mixed>  $op
     */
    private function cotisation(User $collecteur, Membre $membre, array $op, int $centimes, string $cle, Caisse $caisse): int
    {
        $cycle = Cycle::query()->find((int) ($op['cycle_id'] ?? 0)) ?? throw new CollecteRefusee('Cycle introuvable.');
        $parts = isset($op['parts']) ? (int) $op['parts'] : null;

        return $this->cotiser->executer($collecteur, $caisse->compte($cycle->devise), $cycle, $membre,
            new Montant(max(0, $centimes), $cycle->devise), $parts, new Montant(max(0, (int) ($op['fonds_social_centimes'] ?? 0)), $cycle->devise), $cle);
    }

    /**
     * @param  array<string, mixed>  $op
     */
    private function journaliser(LotSync $lot, array $op, string $statut, ?string $raison, ?int $operationId): OperationSync
    {
        $ligne = new OperationSync;
        $ligne->forceFill([
            'organisation_id' => $lot->organisation_id, 'lot_id' => $lot->id, 'cle' => (string) $op['cle'], 'type' => (string) $op['type'],
            'membre_id' => isset($op['membre_id']) ? (int) $op['membre_id'] : null, 'statut' => $statut, 'raison' => $raison,
            'operation_id' => $operationId, 'donnees' => $op,
            'saisi_le' => isset($op['saisi_le']) ? CarbonImmutable::parse((string) $op['saisi_le'])->utc() : null,
        ])->save();

        return $ligne;
    }

    /**
     * @return array<string, mixed>
     */
    private function resultat(OperationSync $ligne, bool $rejoue): array
    {
        $recu = $ligne->operation_id === null ? null
            : (MouvementEpargne::query()->with('recu')->where('operation_id', $ligne->operation_id)->first()?->recu?->getAttribute('numero')
                ?? Remboursement::query()->with('recu')->where('operation_id', $ligne->operation_id)->first()?->recu?->getAttribute('numero'));

        return array_filter([
            'cle' => $ligne->getAttribute('cle'),
            'statut' => $ligne->statut === 'cree' && $rejoue ? 'deja_traite' : $ligne->statut,
            'raison' => $ligne->raison,
            'operation_id' => $ligne->operation_id,
            'recu' => $recu,
        ], fn ($v) => $v !== null);
    }
}
