<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\ShelfLevelPreference;
use App\Models\Tenant;
use Callcocam\LaravelRaptorPlannerate\Enums\ShelfLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShelfLevelPreference>
 */
class ShelfLevelPreferenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'category_id' => Category::factory(),
            'preferred_level' => $this->faker->randomElement(ShelfLevel::cases()),
        ];
    }

    public function default(): static
    {
        return $this->state(function (array $attributes) {
            return ['category_id' => null];
        });
    }

    public function withLevel(ShelfLevel $level): static
    {
        return $this->state(function (array $attributes) use ($level) {
            return ['preferred_level' => $level];
        });
    }
}
