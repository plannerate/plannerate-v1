<?php

namespace Database\Factories;

use App\Models\Planogram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola>
 */
class GondolaFactory extends Factory
{
    protected $model = \Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola::class;

    public function definition(): array
    {
        return [
            'planogram_id' => Planogram::factory(),
            'name' => fake()->words(2, true),
            'location' => fake()->randomElement(['Corredor A', 'Corredor B', 'Corredor C']),
            'side' => fake()->randomElement(['A', 'B', '1', '2']),
            'flow' => fake()->randomElement(['left_to_right', 'right_to_left']),
            'alignment' => fake()->randomElement(['left', 'right', 'center', 'justify']),
            'scale_factor' => 3,
            'status' => 'draft',
            'num_modulos' => 1,
        ];
    }
}
