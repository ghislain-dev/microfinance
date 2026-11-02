<?php

declare(strict_types=1);

namespace App\Domain\Membres\Actions;

use App\Domain\Membres\Models\Membre;
use App\Domain\Organisation\Acces\Role;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use App\Support\Audit\JournalAudit;
use Illuminate\Support\Facades\DB;

/**
 * Accès à l'application mobile d'un membre (ADR 022), créé ou réinitialisé au guichet par le gérant ou
 * le caissier : mot de passe provisoire montré une seule fois, à changer à la première connexion ;
 * une réinitialisation révoque les jetons existants.
 */
final class CreerAccesMobile
{
    // Sans caractères ambigus (0/O, 1/l/I) : le mot de passe est recopié à la main par le membre.
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyzACDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(private readonly JournalAudit $journal) {}

    /**
     * @return array{User, string} l'utilisateur et le mot de passe provisoire en clair
     */
    public function executer(User $acteur, Membre $membre): array
    {
        AutoriserDansOrganisation::autoriser($acteur, 'accesMobile', $membre);
        $provisoire = self::motDePasse();

        $utilisateur = DB::transaction(function () use ($membre, $provisoire) {
            $utilisateur = User::query()->where('membre_id', $membre->id)->lockForUpdate()->first() ?? new User;
            $nouveau = ! $utilisateur->exists;
            $utilisateur->forceFill([
                'organisation_id' => $membre->organisation_id,
                'membre_id' => $membre->id,
                'name' => $membre->nomComplet(),
                'password' => $provisoire,
                'mot_de_passe_provisoire' => true,
                'actif' => true,
            ])->save();
            $utilisateur->tokens()->delete();
            if ($nouveau) {
                setPermissionsTeamId($membre->organisation_id);
                $utilisateur->assignRole(Role::Membre->value);
            }

            return $utilisateur;
        });
        $this->journal->enregistrer('membres.acces_mobile', $membre);

        return [$utilisateur, $provisoire];
    }

    private static function motDePasse(): string
    {
        $texte = '';
        for ($i = 0; $i < 10; $i++) {
            $texte .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $texte;
    }
}
