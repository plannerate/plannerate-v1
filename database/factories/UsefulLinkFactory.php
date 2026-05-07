<?php

namespace Database\Factories;

use App\Models\UsefulLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsefulLink>
 */
class UsefulLinkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst((string) $this->faker->words(2, true)),
            'url' => $this->faker->url(),
            'logo' => $this->faker->optional()->imageUrl(128, 128),
            'description' => $this->faker->optional()->sentence(),
            'show_on_tenant_dashboard' => $this->faker->boolean(70),
        ];
    }
}
