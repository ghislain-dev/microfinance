<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Organisation\Models\Agence;
use App\Domain\Organisation\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agence>
 */
class AgenceFactory extends Factory
{
    protected $model = Agence::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'nom' => 'Agence '.fake()->city(),
            'code' => strtoupper(fake()->unique()->bothify('AG-###')),
            'actif' => true,
        ];
    }
}
