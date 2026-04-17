<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Provider>
 */
class ProviderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => config('app.current_tenant_id') ?? \Illuminate\Support\Str::ulid(),
            'user_id' => \Illuminate\Support\Str::ulid(),
            'code' => fake()->unique()->numerify('PROV####'),
            'name' => fake()->company(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'street' => fake()->streetName(),
            'number' => fake()->buildingNumber(),
            'complement' => fake()->optional()->secondaryAddress(),
            'neighborhood' => fake()->word(),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'zip' => fake()->postcode(),
            'cnpj' => fake()->numerify('##############'), // 14 digits
            'status' => 'published',
            'is_default' => 'N',
            'description' => fake()->optional()->sentence(),
        ];
    }
}
