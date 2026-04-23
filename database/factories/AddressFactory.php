<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => $this->faker->randomElement(['billing', 'shipping']),
            'tenant_id' => null,
            'user_id' => null,
            'addressable_type' => Store::class,
            'addressable_id' => null,
            'name' => $this->faker->words(2, true),
            'zip_code' => '01001-000',
            'street' => $this->faker->streetName(),
            'number' => (string) $this->faker->numberBetween(1, 9999),
            'complement' => $this->faker->optional()->secondaryAddress(),
            'reference' => $this->faker->optional()->sentence(3),
            'additional_information' => $this->faker->optional()->sentence(4),
            'district' => $this->faker->citySuffix(),
            'city' => $this->faker->city(),
            'country' => 'Brasil',
            'state' => 'SP',
            'is_default' => false,
            'status' => 'draft',
        ];
    }
}
