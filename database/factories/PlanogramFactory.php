<?php

namespace Database\Factories;

use App\Models\Planogram;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Planogram>
 */
class PlanogramFactory extends Factory
{
    protected $model = Planogram::class;

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
            'store_id' => null,
            'cluster_id' => null,
            'category_id' => null,
            'template_id' => fake()->optional()->lexify('template-????'),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'type' => fake()->randomElement(['realograma', 'planograma']),
            'start_date' => fake()->optional()->date(),
            'end_date' => fake()->optional()->date(),
            'order' => fake()->numberBetween(0, 10),
            'description' => fake()->optional()->sentence(),
            'status' => fake()->randomElement(['draft', 'published']),
        ];
    }
}
