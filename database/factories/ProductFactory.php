<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'image_id' => null,
            'category_id' => Category::factory(),
            'client_id' => null,
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'ean' => fake()->unique()->ean13(),
            'codigo_erp' => fake()->bothify('ERP-####'),
            'stackable' => fake()->boolean(),
            'perishable' => fake()->boolean(),
            'flammable' => fake()->boolean(),
            'hangable' => fake()->boolean(),
            'description' => fake()->sentence(),
            'sales_status' => fake()->randomElement(['active', 'inactive', 'discontinued']),
            'sales_purchases' => fake()->randomElement(['available', 'unavailable', 'limited']),
            'status' => fake()->randomElement(['draft', 'published', 'synced', 'error']),
            'sync_source' => fake()->randomElement(['api', 'manual', 'import']),
            'sync_at' => fake()->dateTimeBetween('-1 month'),
            'no_sales' => fake()->boolean(),
            'no_purchases' => fake()->boolean(),
            'url' => fake()->optional()->url(),
            'type' => fake()->optional()->word(),
            'reference' => fake()->optional()->bothify('REF-####'),
            'fragrance' => fake()->optional()->word(),
            'flavor' => fake()->optional()->word(),
            'color' => fake()->optional()->safeColorName(),
            'brand' => fake()->optional()->company(),
            'subbrand' => fake()->optional()->word(),
            'packaging_type' => fake()->optional()->word(),
            'packaging_size' => fake()->optional()->word(),
            'measurement_unit' => fake()->optional()->randomElement(['ml', 'g', 'kg', 'l']),
            'packaging_content' => fake()->optional()->word(),
            'unit_measure' => fake()->optional()->randomElement(['un', 'cx', 'pct']),
            'auxiliary_description' => fake()->optional()->sentence(),
            'additional_information' => fake()->optional()->sentence(),
            'sortiment_attribute' => fake()->optional()->word(),
            'dimensions_ean' => fake()->optional()->ean13(),
            'width' => fake()->optional()->randomFloat(2, 1, 100),
            'height' => fake()->optional()->randomFloat(2, 1, 100),
            'depth' => fake()->optional()->randomFloat(2, 1, 100),
            'weight' => fake()->optional()->randomFloat(2, 1, 5000),
            'unit' => 'cm',
            'dimensions_status' => fake()->randomElement(['draft', 'published']),
            'dimensions_description' => fake()->optional()->sentence(),
        ];
    }
}
