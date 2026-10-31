<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Organisation\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organisation>
 */
class OrganisationFactory extends Factory
{
    protected $model = Organisation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom' => fake()->company(),
            'code' => strtoupper(fake()->unique()->bothify('ORG-####')),
            'actif' => true,
        ];
    }
}
