<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Organisation\Models\Agence;
use App\Domain\Organisation\Models\Organisation;
use App\Models\User;

/**
 * Jeu de données des tests d'accès croisé : deux organisations A et B,
 * chacune avec un utilisateur et une agence.
 */
final readonly class DeuxOrganisations
{
    public function __construct(
        public Organisation $orgA,
        public Organisation $orgB,
        public User $userA,
        public User $userB,
        public Agence $agenceA,
        public Agence $agenceB,
    ) {}

    public static function creer(): self
    {
        $orgA = Organisation::factory()->create();
        $orgB = Organisation::factory()->create();

        return new self(
            $orgA,
            $orgB,
            User::factory()->for($orgA)->create(),
            User::factory()->for($orgB)->create(),
            Agence::factory()->for($orgA)->create(),
            Agence::factory()->for($orgB)->create(),
        );
    }
}
