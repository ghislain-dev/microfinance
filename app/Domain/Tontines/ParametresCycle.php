<?php

declare(strict_types=1);

namespace App\Domain\Tontines;

use App\Domain\Tontines\Models\Cycle;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Applique les paramètres saisis à un cycle en préparation (seules les clés fournies changent)
 * et contrôle leur cohérence avec le type de groupe.
 */
final class ParametresCycle
{
    private const MONTANTS = ['montant_cotisation', 'cotisation_fonds_social', 'penalite_absence', 'penalite_retard', 'penalite_impaye'];

    /**
     * @param  array<string, mixed>  $donnees
     */
    public static function appliquer(Cycle $cycle, TypeGroupe $type, array $donnees): void
    {
        if (array_key_exists('devise', $donnees)) {
            $cycle->setAttribute('devise', Devise::from((string) $donnees['devise']));
        }
        if (array_key_exists('periodicite', $donnees)) {
            $cycle->setAttribute('periodicite', Periodicite::from((string) $donnees['periodicite']));
        }
        if (array_key_exists('date_debut', $donnees)) {
            $cycle->setAttribute('date_debut', CarbonImmutable::parse((string) $donnees['date_debut'])->toDateString());
        }
        foreach (self::MONTANTS as $champ) {
            if (array_key_exists($champ, $donnees)) {
                $cycle->setAttribute($champ, Montant::depuisSaisie((string) $donnees[$champ], $cycle->devise));
            }
        }
        if (array_key_exists('mode_tours', $donnees)) {
            $cycle->setAttribute('mode_tours', $donnees['mode_tours'] === null ? null : ModeTours::from((string) $donnees['mode_tours']));
        }
        if (array_key_exists('nombre_seances', $donnees)) {
            $cycle->setAttribute('nombre_seances', $donnees['nombre_seances'] === null ? null : (int) $donnees['nombre_seances']);
        }

        if (! $cycle->montant_cotisation->estPositif()) {
            throw new InvalidArgumentException($type === TypeGroupe::Avec ? 'Le prix de la part doit être positif.' : 'La cotisation doit être positive.');
        }
        if ($type === TypeGroupe::Avec) {
            // AVEC : 1 à 5 parts par séance (décision du 2026-10-31), nombre de séances fixé d'avance, pas de tours.
            if ($cycle->nombre_seances === null || $cycle->nombre_seances < 1) {
                throw new InvalidArgumentException('Un cycle AVEC doit indiquer son nombre de séances.');
            }
            $cycle->forceFill(['parts_min' => 1, 'parts_max' => 5, 'mode_tours' => null]);
        } else {
            if ($cycle->mode_tours === null) {
                throw new InvalidArgumentException('Choisissez le mode d’attribution des tours.');
            }
            $cycle->forceFill(['parts_min' => 1, 'parts_max' => 1, 'nombre_seances' => null]);
        }
    }
}
