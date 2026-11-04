<?php

declare(strict_types=1);

namespace App\Domain\Comptabilite;

use App\Domain\Comptabilite\Models\CorrespondanceSyscohada;
use App\Domain\Finance\Models\Compte;

/**
 * Correspondance entre le plan interne et le SYSCOHADA (ADR 023). Une rubrique est la nature du compte, ou
 * pour un compte interne particulier (produits, charges, créances) le début de son numéro. La proposition
 * par défaut est modifiable par le comptable ; elle reste à faire valider.
 */
final class PlanSyscohada
{
    /** @var array<string, array{0: string, 1: string}> rubrique => [numéro, libellé] */
    public const PROPOSITION = [
        'caisse' => ['5711', 'Caisses'],
        'epargne' => ['4712', 'Dépôts d’épargne des membres'],
        'parts_sociales' => ['1013', 'Capital souscrit, appelé, versé (parts sociales)'],
        'cycle_tontine' => ['4713', 'Fonds des cycles de tontine'],
        'fonds_social' => ['4714', 'Fonds social des tontines'],
        'pret' => ['2748', 'Prêts aux membres'],
        'creance_membre' => ['4718', 'Créances sur membres (pénalités dues)'],
        'creance_personnel' => ['4211', 'Personnel, avances (manquants de caisse et de collecte)'],
        'position_change' => ['4719', 'Position de change'],
        'fonds_propres' => ['1301', 'Excédents en instance d’affectation'],
        'produit' => ['7068', 'Autres produits'],
        'produit_interets_pret' => ['7712', 'Intérêts sur prêts'],
        'produit_penalites_pret' => ['7718', 'Pénalités de retard sur prêts'],
        'produit_frais_retrait' => ['7061', 'Commissions sur retraits'],
        'produit_change' => ['7761', 'Gains de change'],
        'produit_excedents_caisse' => ['7588', 'Excédents de caisse'],
        'produit_encheres_tontine' => ['7062', 'Remises d’enchères de tontine'],
        'charge' => ['6588', 'Autres charges'],
        'charge_interets_epargne' => ['6742', 'Intérêts servis sur l’épargne'],
    ];

    /** Comptes internes reconnus au début de leur numéro (le plus précis d'abord). */
    private const PREFIXES = [
        'PRODUIT-INTERETS-PRET-' => 'produit_interets_pret',
        'PRODUIT-PENALITES-PRET-' => 'produit_penalites_pret',
        'PRODUIT-FRAIS-RETRAIT-' => 'produit_frais_retrait',
        'PRODUIT-CHANGE-' => 'produit_change',
        'PRODUIT-EXCEDENTS-CAISSE-' => 'produit_excedents_caisse',
        'PRODUIT-ENCHERES-TONTINE-' => 'produit_encheres_tontine',
        'CHARGE-INTERETS-EPARGNE-' => 'charge_interets_epargne',
    ];

    /** @var array<string, array{0: string, 1: string}>|null */
    private ?array $correspondances = null;

    public function rubrique(Compte $compte): string
    {
        foreach (self::PREFIXES as $prefixe => $rubrique) {
            if (str_starts_with($compte->numero, $prefixe)) {
                return $rubrique;
            }
        }

        return $compte->nature->value;
    }

    public function numero(Compte $compte): string
    {
        return $this->correspondance($this->rubrique($compte))[0];
    }

    /**
     * @return array{0: string, 1: string} [numéro, libellé] de la rubrique, choisi par le comptable ou proposé
     */
    public function correspondance(string $rubrique): array
    {
        return $this->toutes()[$rubrique] ?? ['?', $rubrique];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function toutes(): array
    {
        return $this->correspondances ??= [...self::PROPOSITION, ...CorrespondanceSyscohada::query()->get()
            ->mapWithKeys(fn (CorrespondanceSyscohada $c) => [(string) $c->getAttribute('rubrique') => [(string) $c->getAttribute('numero'), (string) $c->getAttribute('libelle')]])
            ->all()];
    }

    public function oublier(): void
    {
        $this->correspondances = null;
    }
}
