<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Comptes\Sens;
use App\Domain\Finance\Models\Compte;
use App\Domain\Organisation\Models\Organisation;
use App\Support\Audit\JournalAudit;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use Illuminate\Support\Facades\DB;

/**
 * Contrôle de nuit (CLAUDE.md §4.4) : solde_cache de chaque compte = somme de ses écritures,
 * et Σ débits = Σ crédits par devise dans chaque organisation.
 */
final class VerificateurSoldes
{
    public function __construct(private readonly JournalAudit $journal) {}

    /**
     * @return list<EcartSolde>
     */
    public function ecartsDeCache(Organisation $organisation, bool $corriger = false): array
    {
        $totaux = DB::table('ecritures')
            ->where('organisation_id', $organisation->id)
            ->groupBy('compte_id')
            ->selectRaw('compte_id, SUM(debit) AS debits, SUM(credit) AS credits')
            ->get()->keyBy('compte_id');

        $ecarts = [];
        Compte::withoutGlobalScopes()->where('organisation_id', $organisation->id)
            ->chunkById(500, function ($comptes) use ($totaux, &$ecarts) {
                foreach ($comptes as $compte) {
                    $ligne = $totaux->get($compte->id);
                    $reel = $this->soldeDansLeSensNormal($compte, (int) ($ligne->debits ?? 0), (int) ($ligne->credits ?? 0));
                    if (! $reel->egal($compte->solde_cache)) {
                        $ecarts[] = new EcartSolde($compte, $compte->solde_cache, $reel);
                    }
                }
            });

        if ($corriger) {
            foreach ($ecarts as $ecart) {
                $this->corriger($ecart->compte);
            }
        }

        return $ecarts;
    }

    /**
     * Devises dont les débits ne valent pas les crédits dans l'organisation.
     *
     * @return list<Devise>
     */
    public function devisesDesequilibrees(Organisation $organisation): array
    {
        return DB::table('ecritures')
            ->where('organisation_id', $organisation->id)
            ->groupBy('devise')
            ->selectRaw('devise, SUM(debit) AS debits, SUM(credit) AS credits')
            ->get()
            ->filter(fn (object $ligne) => (int) $ligne->debits !== (int) $ligne->credits)
            ->map(fn (object $ligne) => Devise::from((string) $ligne->devise))
            ->values()->all();
    }

    /**
     * Réaligne le cache sur le grand livre, sous verrou (une opération peut être en cours).
     */
    private function corriger(Compte $compte): void
    {
        DB::transaction(function () use ($compte) {
            $verrouille = Compte::withoutGlobalScopes()->whereKey($compte->id)->lockForUpdate()->firstOrFail();
            $totaux = DB::table('ecritures')->where('compte_id', $verrouille->id)
                ->selectRaw('COALESCE(SUM(debit), 0) AS debits, COALESCE(SUM(credit), 0) AS credits')->first();
            $reel = $this->soldeDansLeSensNormal($verrouille, (int) ($totaux->debits ?? 0), (int) ($totaux->credits ?? 0));

            $avant = $verrouille->solde_cache->centimes;
            $verrouille->solde_cache = $reel;
            $verrouille->save();

            $this->journal->enregistrer('comptes.cache_corrige', $verrouille,
                avant: ['solde_cache' => $avant], apres: ['solde_cache' => $reel->centimes]);
        });
    }

    private function soldeDansLeSensNormal(Compte $compte, int $debits, int $credits): Montant
    {
        return new Montant($compte->nature->sens() === Sens::Debit ? $debits - $credits : $credits - $debits, $compte->devise);
    }
}
