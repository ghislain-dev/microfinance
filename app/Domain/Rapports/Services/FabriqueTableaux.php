<?php

declare(strict_types=1);

namespace App\Domain\Rapports\Services;

use App\Domain\Caisse\Models\SessionCaisse;
use App\Domain\Collecte\Models\VersementCollecte;
use App\Domain\Comptabilite\Services\EtatsComptables;
use App\Domain\Credit\Models\EcheancePret;
use App\Domain\Credit\Models\Pret;
use App\Domain\Credit\Services\IndicateursCredit;
use App\Domain\Credit\StatutPret;
use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Epargne\Models\MouvementEpargne;
use App\Domain\Finance\Services\LecturesComptables;
use App\Domain\Rapports\Models\Export;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use App\Support\Montant\Pourcentage;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Met en tableau chacun des rapports exportables (ADR 023), à partir des services métier.
 */
final class FabriqueTableaux
{
    public function __construct(
        private readonly EtatsComptables $etats,
        private readonly LecturesComptables $lectures,
        private readonly IndicateursCredit $indicateurs,
    ) {}

    public function pour(Export $export): Tableau
    {
        $p = $export->parametres;

        return match ($export->type) {
            'balance' => $this->balance(Devise::from($p['devise']), $this->date($p['du']), $this->date($p['au'])),
            'grand_livre' => $this->grandLivre((string) $p['rubrique'], Devise::from($p['devise']), $this->date($p['du']), $this->date($p['au'])),
            'journal' => $this->journal(Devise::from($p['devise']), $this->date($p['du']), $this->date($p['au'])),
            'releve' => $this->releve((int) $p['compte_epargne_id'], $this->date($p['du']), $this->date($p['au'])),
            'portefeuille' => $this->portefeuille(Devise::from($p['devise'])),
            'caisse' => $this->caisse($this->date($p['du']), $this->date($p['au'])),
            default => throw new InvalidArgumentException("Rapport inconnu : {$export->type}."),
        };
    }

    private function balance(Devise $devise, CarbonImmutable $du, CarbonImmutable $au): Tableau
    {
        $balance = $this->etats->balance($devise, $du, $au);
        $lignes = array_map(fn ($l) => [$l->syscohada, $l->libelle, $this->m($l->ouverture), $this->m($l->debit), $this->m($l->credit), $this->m($l->cloture)], $balance->lignes);
        $lignes[] = ['Totaux', '', '', $this->m($balance->totalDebit), $this->m($balance->totalCredit), ''];

        return new Tableau("Balance {$devise->value}", $this->periode($du, $au).' · soldes positifs débiteurs, négatifs créditeurs',
            ['Compte', 'Libellé', 'Solde d’ouverture', 'Débit', 'Crédit', 'Solde de clôture'], $lignes, [2, 3, 4, 5]);
    }

    private function grandLivre(string $rubrique, Devise $devise, CarbonImmutable $du, CarbonImmutable $au): Tableau
    {
        $livre = $this->etats->grandLivre($rubrique, $devise, $du, $au);
        $lignes = [['', 'Solde d’ouverture', '', '', '', '', $this->m($livre->ouverture)]];
        foreach ($livre->lignes as $l) {
            $lignes[] = [$l->date->format('d/m/Y'), "n° {$l->operationId} {$l->type}", $l->compte, (string) $l->motif,
                $l->debit->estZero() ? '' : $this->m($l->debit), $l->credit->estZero() ? '' : $this->m($l->credit), $this->m($l->solde)];
        }
        $lignes[] = ['', 'Solde de clôture', '', '', '', '', $this->m($livre->cloture)];

        return new Tableau("Grand livre {$livre->syscohada} {$livre->libelle} ({$devise->value})", $this->periode($du, $au),
            ['Date', 'Opération', 'Compte', 'Motif', 'Débit', 'Crédit', 'Solde'], $lignes, [4, 5, 6]);
    }

    private function journal(Devise $devise, CarbonImmutable $du, CarbonImmutable $au): Tableau
    {
        $lignes = [];
        $page = 1;
        do {
            $operations = $this->etats->journal($devise, $du, $au, 500, $page);
            foreach ($operations->items() as $o) {
                foreach ($o->ecritures as $i => $e) {
                    $lignes[] = [$i === 0 ? $o->date->format('d/m/Y') : '', $i === 0 ? "n° {$o->id} {$o->type}" : '', $e->compte, $e->syscohada,
                        $e->debit->estZero() ? '' : $this->m($e->debit), $e->credit->estZero() ? '' : $this->m($e->credit)];
                }
            }
            $page++;
        } while ($page <= $operations->lastPage());

        return new Tableau("Journal {$devise->value}", $this->periode($du, $au), ['Date', 'Opération', 'Compte', 'SYSCOHADA', 'Débit', 'Crédit'], $lignes, [4, 5]);
    }

    private function releve(int $compteId, CarbonImmutable $du, CarbonImmutable $au): Tableau
    {
        $compte = CompteEpargne::query()->with(['membre', 'type', 'compte'])->findOrFail($compteId);
        $devise = $compte->type->devise;
        $avant = $this->lectures->totauxParCompte($devise, null, $du->subDay())[$compte->compte_id] ?? ['debit' => 0, 'credit' => 0];
        $solde = $avant['credit'] - $avant['debit']; // épargne : solde dans le sens crédit
        $mouvements = $this->lectures->mouvements([(int) $compte->compte_id], $devise, $du, $au);
        $libelles = MouvementEpargne::query()->where('compte_epargne_id', $compte->id)
            ->whereIn('operation_id', array_column($mouvements, 'operation_id'))->get()->keyBy('operation_id');

        $lignes = [["Solde au {$du->subDay()->format('d/m/Y')}", '', '', '', $this->m(new Montant($solde, $devise))]];
        foreach ($mouvements as $m) {
            $solde += $m['credit'] - $m['debit'];
            $libelle = $libelles->get($m['operation_id'])?->libelleType() ?? ($m['motif'] ?? $m['type']);
            $lignes[] = [$m['date']->format('d/m/Y'), $libelle, $m['credit'] > 0 ? $this->m(new Montant($m['credit'], $devise)) : '',
                $m['debit'] > 0 ? $this->m(new Montant($m['debit'], $devise)) : '', $this->m(new Montant($solde, $devise))];
        }
        $lignes[] = ["Solde au {$au->format('d/m/Y')}", '', '', '', $this->m(new Montant($solde, $devise))];

        return new Tableau("Relevé du compte {$compte->numero} ({$devise->value})",
            "{$compte->membre->numero} — {$compte->membre->nomComplet()} · ".$this->periode($du, $au),
            ['Date', 'Opération', 'Entrée', 'Sortie', 'Solde'], $lignes, [2, 3, 4]);
    }

    private function portefeuille(Devise $devise): Tableau
    {
        $aujourdhui = CarbonImmutable::today();
        $lignes = [];
        Pret::query()->with(['membre', 'echeances'])->where('devise', $devise->value)->where('statut', StatutPret::EnCours->value)->orderBy('numero')
            ->each(function (Pret $pret) use (&$lignes, $aujourdhui) {
                $encours = $pret->echeances->reduce(fn (Montant $t, EcheancePret $e) => $t->plus($e->capitalRestant()), Montant::zero($pret->devise));
                $resteDu = $pret->echeances->reduce(fn (Montant $t, EcheancePret $e) => $t->plus($e->resteDu()), Montant::zero($pret->devise));
                $plusAncienne = $pret->echeances->first(fn (EcheancePret $e) => $e->payee_le === null && $e->date_echeance->lt($aujourdhui));
                $retard = $plusAncienne === null ? 0 : (int) CarbonImmutable::parse($plusAncienne->date_echeance)->diffInDays($aujourdhui);
                $lignes[] = [$pret->numero, $pret->membre->nomComplet(), $this->m($pret->montant), $this->m($encours), $this->m($resteDu), (string) $retard,
                    $pret->date_decaissement?->format('d/m/Y') ?? ''];
            });
        foreach ([30, 90] as $jours) {
            $par = $this->indicateurs->par($jours, $devise);
            $lignes[] = ["PAR {$jours}", Pourcentage::formate($par->tauxPb), $this->m($par->aRisque), $this->m($par->encours), '', '', ''];
        }

        return new Tableau("Portefeuille de crédit {$devise->value}", 'Au '.$aujourdhui->format('d/m/Y'),
            ['Prêt', 'Membre', 'Montant', 'Encours', 'Reste dû', 'Jours de retard', 'Décaissé le'], $lignes, [2, 3, 4, 5]);
    }

    private function caisse(CarbonImmutable $du, CarbonImmutable $au): Tableau
    {
        $lignes = [];
        SessionCaisse::query()->with(['caisse', 'caissier', 'ecarts'])->whereBetween('ouverte_le', [$du->startOfDay(), $au->endOfDay()])->orderBy('ouverte_le')
            ->each(function (SessionCaisse $s) use (&$lignes) {
                $ecarts = $s->ecarts->map(fn ($e) => $e->ecart->formate())->implode(' · ');
                $lignes[] = ['Session', $s->caisse->code, $s->caissier->name, $s->ouverte_le->format('d/m/Y H:i'),
                    $s->getAttribute('fermee_le')?->format('d/m/Y H:i') ?? '', $s->statut->libelle(), $ecarts];
            });
        VersementCollecte::query()->with('collecteur')->whereBetween('created_at', [$du->startOfDay(), $au->endOfDay()])->orderBy('id')
            ->each(function (VersementCollecte $v) use (&$lignes) {
                $lignes[] = ['Versement collecteur', '', $v->collecteur->name, $v->created_at?->format('d/m/Y H:i') ?? '', '',
                    $v->statut === 'valide' ? 'Validé' : 'Écart à justifier', "théorique {$v->theorique->formate()} · compté {$v->compte->formate()}"];
            });

        return new Tableau('Caisse et collecte', $this->periode($du, $au), ['Nature', 'Caisse', 'Personne', 'Ouverture', 'Fermeture', 'Statut', 'Écarts'], $lignes);
    }

    /** Montant sans la devise (indiquée dans le titre). */
    private function m(Montant $montant): string
    {
        return substr($montant->formate(), 0, -4);
    }

    private function periode(CarbonImmutable $du, CarbonImmutable $au): string
    {
        return "Du {$du->format('d/m/Y')} au {$au->format('d/m/Y')} (dates de valeur)";
    }

    private function date(mixed $valeur): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $valeur);
    }
}
