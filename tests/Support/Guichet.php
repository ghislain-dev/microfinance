<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Caisse\Actions\CreerCaisse;
use App\Domain\Caisse\Models\Caisse;
use App\Domain\Organisation\Models\Agence;
use App\Domain\Organisation\Models\Organisation;
use App\Models\User;

/**
 * Une agence, une caisse et un caissier rattaché à l'agence.
 */
final readonly class Guichet
{
    public function __construct(
        public Organisation $organisation,
        public Agence $agence,
        public Caisse $caisse,
        public User $caissier,
        public User $gerant,
    ) {}

    public static function creer(): self
    {
        $gerant = Acteurs::avecRole('gerant');
        $agence = Agence::factory()->for($gerant->organisation)->create(['code' => 'BUK']);
        $caissier = Acteurs::avecRole('caissier', $gerant->organisation);
        foreach ([$gerant, $caissier] as $utilisateur) {
            $utilisateur->agences()->attach($agence, ['organisation_id' => $agence->organisation_id]);
        }
        auth()->setUser($gerant);
        $caisse = app(CreerCaisse::class)->executer($gerant, $agence, 'G1', 'Guichet 1');
        auth()->setUser($caissier);

        return new self($gerant->organisation, $agence, $caisse, $caissier, $gerant);
    }
}
