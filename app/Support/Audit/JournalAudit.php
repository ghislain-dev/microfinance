<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Point d'entrée unique du journal d'audit : qui, quand, IP, appareil, avant / après.
 */
final class JournalAudit
{
    /** Attributs jamais écrits en clair dans le journal. */
    private const MASQUES = ['password', 'remember_token', 'code_hache', 'token', 'piece_numero', 'piece_empreinte'];

    /**
     * @param  array<string, mixed>|null  $avant
     * @param  array<string, mixed>|null  $apres
     */
    public function enregistrer(
        string $action,
        ?Model $cible = null,
        ?array $avant = null,
        ?array $apres = null,
        ?User $acteur = null,
    ): LigneAudit {
        $acteur ??= Auth::hasUser() ? Auth::user() : null;
        $requete = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        $ligne = new LigneAudit([
            'user_id' => $acteur?->getAuthIdentifier(),
            'action' => $action,
            'cible_type' => $cible?->getTable(),
            'cible_id' => $cible?->getKey(),
            'avant' => $avant === null ? null : $this->masquer($avant),
            'apres' => $apres === null ? null : $this->masquer($apres),
            'ip' => $requete?->ip(),
            'appareil' => $requete === null ? 'console' : mb_substr((string) $requete->userAgent(), 0, 255),
        ]);
        $ligne->organisation_id = $acteur instanceof User ? $acteur->organisation_id : $this->organisationDe($cible);
        $ligne->save();

        return $ligne;
    }

    /**
     * @param  array<string, mixed>  $valeurs
     * @return array<string, mixed>
     */
    private function masquer(array $valeurs): array
    {
        foreach (self::MASQUES as $cle) {
            if (array_key_exists($cle, $valeurs)) {
                $valeurs[$cle] = '[masqué]';
            }
        }

        return $valeurs;
    }

    private function organisationDe(?Model $cible): ?int
    {
        $id = $cible?->getAttribute($cible->getTable() === 'organisations' ? 'id' : 'organisation_id');

        return $id === null ? null : (int) $id;
    }
}
