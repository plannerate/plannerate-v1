<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'category_id' => null,
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'level_name' => fake()->randomElement(['Categoria', 'Subcategoria']),
            'codigo' => fake()->numberBetween(1000, 999999),
            'status' => fake()->randomElement(['draft', 'published', 'importer']),
            'description' => fake()->sentence(),
            'nivel' => fake()->randomElement(['1', '2', '3']),
            'hierarchy_position' => fake()->numberBetween(1, 7),
            'full_path' => fake()->sentence(),
            'hierarchy_path' => ['Root', Str::title($name)],
            'is_placeholder' => false,
        ];
    }
}
