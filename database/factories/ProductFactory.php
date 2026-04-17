<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => '01jjhdwmgkkm8j2cyhmdnxrsqe',
            'user_id' => User::factory(),
            'category_id' => null,
            'name' => fake()->words(3, true),
            'slug' => fake()->unique()->slug(),
            'ean' => fake()->ean13(),
            'codigo_erp' => fake()->optional()->numerify('ERP-#####'),
            'stackable' => fake()->boolean(),
            'perishable' => fake()->boolean(),
            'flammable' => fake()->boolean(),
            'hangable' => fake()->boolean(),
            'description' => fake()->optional()->paragraph(),
            'sales_status' => fake()->randomElement(['active', 'inactive', 'discontinued']),
            'sales_purchases' => fake()->randomElement(['available', 'unavailable', 'limited']),
            'status' => fake()->randomElement(['draft', 'published', 'synced', 'error']),
            'sync_source' => fake()->optional()->randomElement(['api', 'manual', 'import']),
            'sync_at' => fake()->optional()->dateTimeBetween('-1 month', 'now'),
            'no_sales' => fake()->boolean(10),
            'no_purchases' => fake()->boolean(10),
            'url' => fake()->optional()->imageUrl(),
        ];
    }
}
