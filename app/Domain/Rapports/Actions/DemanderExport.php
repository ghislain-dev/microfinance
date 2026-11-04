<?php

declare(strict_types=1);

namespace App\Domain\Rapports\Actions;

use App\Domain\Comptabilite\PlanSyscohada;
use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Rapports\Jobs\GenererExport;
use App\Domain\Rapports\Models\Export;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use App\Support\Montant\Devise;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Enregistre la demande d'un rapport et le met en file d'attente ; le fichier est téléchargé ensuite.
 */
final class DemanderExport
{
    /**
     * @param  array<string, mixed>  $parametres
     */
    public function executer(User $acteur, string $type, string $format, array $parametres): Export
    {
        if (! array_key_exists($type, Export::TYPES) || ! in_array($format, ['pdf', 'csv'], true)) {
            throw new InvalidArgumentException('Rapport ou format inconnu.');
        }
        $parametres = $this->parametres($type, $parametres);
        AutoriserDansOrganisation::autoriser($acteur, 'demander', [Export::class, $type, $parametres]);

        $export = new Export;
        $export->forceFill([
            'organisation_id' => $acteur->organisation_id, 'user_id' => $acteur->id, 'type' => $type, 'format' => $format,
            'parametres' => $parametres, 'statut' => 'en_attente',
        ])->save();
        GenererExport::dispatch($export->id)->afterCommit();

        return $export;
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    private function parametres(string $type, array $p): array
    {
        $date = function (string $champ) use ($p): string {
            $valeur = (string) ($p[$champ] ?? '');
            $d = CarbonImmutable::createFromFormat('!Y-m-d', $valeur);
            if ($d === null || $d->format('Y-m-d') !== $valeur) {
                throw new InvalidArgumentException("Date « {$champ} » attendue au format AAAA-MM-JJ.");
            }

            return $valeur;
        };
        $devise = function () use ($p): string {
            return (Devise::tryFrom((string) ($p['devise'] ?? '')) ?? throw new InvalidArgumentException('Devise : USD ou CDF.'))->value;
        };
        $periode = function () use ($date): array {
            [$du, $au] = [$date('du'), $date('au')];
            if ($au < $du) {
                throw new InvalidArgumentException('La fin de période précède le début.');
            }

            return ['du' => $du, 'au' => $au];
        };

        return match ($type) {
            'balance', 'journal' => ['devise' => $devise(), ...$periode()],
            'grand_livre' => ['rubrique' => array_key_exists((string) ($p['rubrique'] ?? ''), PlanSyscohada::PROPOSITION)
                ? (string) $p['rubrique'] : throw new InvalidArgumentException('Rubrique inconnue.'), 'devise' => $devise(), ...$periode()],
            'releve' => ['compte_epargne_id' => CompteEpargne::query()->findOrFail((int) ($p['compte_epargne_id'] ?? 0))->id, ...$periode()],
            'portefeuille' => ['devise' => $devise()],
            default => $periode(),
        };
    }
}
