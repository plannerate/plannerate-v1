<?php

namespace Database\Factories;

use App\Models\Gondola;
use App\Models\Planogram;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Gondola>
 */
class GondolaFactory extends Factory
{
    protected $model = Gondola::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'planogram_id' => Planogram::factory(),
            'linked_map_gondola_id' => null,
            'linked_map_gondola_category' => null,
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'num_modulos' => fake()->numberBetween(1, 10),
            'location' => fake()->optional()->word(),
            'side' => fake()->optional()->randomElement(['left', 'right']),
            'flow' => fake()->randomElement(['left_to_right', 'right_to_left']),
            'alignment' => fake()->randomElement(['left', 'right', 'center', 'justify']),
            'scale_factor' => fake()->randomFloat(2, 0.5, 2),
            'status' => fake()->randomElement(['draft', 'published']),
        ];
    }
}
