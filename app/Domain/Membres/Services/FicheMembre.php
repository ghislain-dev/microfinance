<?php

declare(strict_types=1);

namespace App\Domain\Membres\Services;

use App\Domain\Membres\Exceptions\PieceDejaEnregistree;
use App\Domain\Membres\Exceptions\TelephoneDejaUtilise;
use App\Domain\Membres\Models\Membre;
use App\Support\Telephone;
use InvalidArgumentException;

/**
 * Normalise une fiche et applique les règles de doublon (2026-10-31) :
 * même pièce = refus ; même téléphone = avertissement à confirmer.
 */
final class FicheMembre
{
    /**
     * @param  array<string, mixed>  $donnees  données validées par le Form Request
     * @return array<string, mixed> attributs prêts à enregistrer (avec piece_empreinte)
     */
    public function preparer(array $donnees, bool $telephonePartageConfirme, ?Membre $exclu = null): array
    {
        $telephone = Telephone::normaliser((string) ($donnees['telephone'] ?? ''))
            ?? throw new InvalidArgumentException('Numéro de téléphone invalide.');

        $attributs = [
            'nom' => trim((string) $donnees['nom']),
            'postnom' => self::facultatif($donnees['postnom'] ?? null),
            'prenom' => self::facultatif($donnees['prenom'] ?? null),
            'sexe' => (string) $donnees['sexe'],
            'date_naissance' => (string) $donnees['date_naissance'],
            'telephone' => $telephone,
            'piece_type' => trim((string) $donnees['piece_type']),
            'piece_numero' => trim((string) $donnees['piece_numero']),
            'adresse' => self::facultatif($donnees['adresse'] ?? null),
        ];
        $attributs['piece_empreinte'] = self::empreintePiece($attributs['piece_type'], $attributs['piece_numero']);

        $memePiece = Membre::query()->where('piece_empreinte', $attributs['piece_empreinte'])
            ->when($exclu, fn ($q) => $q->whereKeyNot($exclu->id))->first();
        if ($memePiece !== null) {
            throw new PieceDejaEnregistree($memePiece);
        }

        if (! $telephonePartageConfirme) {
            $memeTelephone = Membre::query()->where('telephone', $telephone)
                ->when($exclu, fn ($q) => $q->whereKeyNot($exclu->id))->orderBy('id')->limit(10)->get();
            if ($memeTelephone->isNotEmpty()) {
                throw new TelephoneDejaUtilise($memeTelephone);
            }
        }

        return $attributs;
    }

    /**
     * HMAC de la pièce (casse, espaces et ponctuation ignorés) : comparable sans déchiffrer.
     */
    public static function empreintePiece(string $type, string $numero): string
    {
        $canonique = fn (string $texte) => mb_strtoupper(preg_replace('/[\s\-.\/]/u', '', $texte) ?? '');

        return hash_hmac('sha256', $canonique($type).'|'.$canonique($numero), (string) config('app.key'));
    }

    private static function facultatif(mixed $valeur): ?string
    {
        $texte = trim((string) $valeur);

        return $texte === '' ? null : $texte;
    }
}
