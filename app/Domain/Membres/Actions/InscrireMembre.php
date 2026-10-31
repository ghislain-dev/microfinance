<?php

declare(strict_types=1);

namespace App\Domain\Membres\Actions;

use App\Domain\Membres\Exceptions\PieceDejaEnregistree;
use App\Domain\Membres\Models\Membre;
use App\Domain\Membres\Services\FicheMembre;
use App\Domain\Organisation\Models\Agence;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Inscription : numéro séquentiel par agence (« BUK-000045 »), sans trou ni doublon.
 */
final class InscrireMembre
{
    public function __construct(private readonly FicheMembre $fiche) {}

    /**
     * @param  array<string, mixed>  $donnees
     */
    public function executer(User $acteur, Agence $agence, array $donnees, bool $telephonePartageConfirme = false): Membre
    {
        AutoriserDansOrganisation::autoriser($acteur, 'create', [Membre::class, $agence]);
        $attributs = $this->fiche->preparer($donnees, $telephonePartageConfirme);

        try {
            return DB::transaction(function () use ($agence, $attributs) {
                // Verrou sur l'agence : deux inscriptions simultanées ne prennent pas le même numéro.
                $agence = Agence::query()->whereKey($agence->id)->lockForUpdate()->firstOrFail();
                $sequence = (int) Membre::query()->where('agence_id', $agence->id)->max('numero_sequence') + 1;

                $membre = new Membre(Arr::except($attributs, 'piece_empreinte'));
                $membre->forceFill([
                    'organisation_id' => $agence->organisation_id,
                    'agence_id' => $agence->id,
                    'numero_sequence' => $sequence,
                    'numero' => $agence->code.'-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
                    'piece_empreinte' => $attributs['piece_empreinte'],
                ]);
                $membre->save();

                return $membre->refresh();
            }, attempts: 3);
        } catch (UniqueConstraintViolationException $e) {
            // Même pièce enregistrée au même instant par une autre requête.
            $existant = Membre::query()->where('piece_empreinte', $attributs['piece_empreinte'])->first();
            throw $existant !== null ? new PieceDejaEnregistree($existant) : $e;
        }
    }
}
