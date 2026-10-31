<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Membres\Models\Membre;
use App\Domain\Membres\Services\FicheMembre;
use App\Domain\Organisation\Models\Agence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pour les tests d'affichage ; l'inscription réelle passe par InscrireMembre.
 *
 * @extends Factory<Membre>
 */
class MembreFactory extends Factory
{
    protected $model = Membre::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sequence = fake()->unique()->numberBetween(1, 999_999);
        $piece = 'CE-'.fake()->unique()->numerify('########');

        return [
            'agence_id' => Agence::factory(),
            'organisation_id' => fn (array $a) => Agence::withoutGlobalScopes()->findOrFail($a['agence_id'])->organisation_id,
            'numero_sequence' => $sequence,
            'numero' => 'FAB-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
            'nom' => fake()->lastName(),
            'postnom' => fake()->lastName(),
            'prenom' => fake()->firstName(),
            'sexe' => fake()->randomElement(['M', 'F']),
            'date_naissance' => fake()->date('Y-m-d', '-18 years'),
            'telephone' => '+24381'.fake()->numerify('#######'),
            'piece_type' => 'Carte d’électeur',
            'piece_numero' => $piece,
            'piece_empreinte' => FicheMembre::empreintePiece('Carte d’électeur', $piece),
            'adresse' => fake()->city(),
            'statut' => 'actif',
        ];
    }
}
