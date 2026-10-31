<?php

declare(strict_types=1);

namespace App\Domain\Organisation\Services;

use App\Domain\Organisation\Acces\Droit;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Hash;

/**
 * Première étape de connexion : e-mail + mot de passe. La session (ou le jeton) n'est ouverte
 * qu'après le code OTP.
 */
final class Authentificateur
{
    /**
     * Renvoie l'utilisateur si les identifiants sont bons et le compte autorisé, sinon null.
     * Même réponse dans tous les cas d'échec : on ne révèle pas si l'e-mail existe.
     */
    public function verifier(string $email, string $motDePasse, bool $accesWeb): ?User
    {
        $utilisateur = User::query()->with('organisation')->where('email', $email)->first();

        // Hachage calculé même si l'e-mail est inconnu : temps de réponse identique.
        $motDePasseBon = Hash::check($motDePasse, $utilisateur->password ?? Hash::make('compte-inexistant'));

        if ($utilisateur === null || ! $motDePasseBon || ! $this->autorise($utilisateur, $accesWeb)) {
            event(new Failed($accesWeb ? 'web' : 'sanctum', $utilisateur, ['email' => $email]));

            return null;
        }

        return $utilisateur;
    }

    public function autorise(User $utilisateur, bool $accesWeb): bool
    {
        if (! $utilisateur->actif || ! $utilisateur->organisation?->actif) {
            return false;
        }

        if (! $accesWeb) {
            return true;
        }

        // Accès web réservé au personnel : le membre passe par l'application mobile.
        setPermissionsTeamId($utilisateur->organisation_id);

        return $utilisateur->can(Droit::TableauDeBordVoir->value);
    }
}
