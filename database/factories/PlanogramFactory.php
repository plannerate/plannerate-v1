<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Planogram>
 */
class PlanogramFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'type' => fake()->randomElement(['realograma', 'planograma']),
            'description' => fake()->sentence(),
            'status' => 'draft',
            'start_date' => now(),
            'end_date' => now()->addDays(30),
            'order' => 0,
        ];
    }
}
