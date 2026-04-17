<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Store>
 */
class StoreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => Str::ulid()->toString(),
            'tenant_id' => config('app.current_tenant_id', Str::ulid()->toString()),
            'user_id' => \App\Models\User::factory(),
            'client_id' => \App\Models\Client::factory(),
            'name' => fake()->company() . ' - Loja',
            'document' => fake()->numerify('##.###.###/####-##'),
            'status' => 'published',
        ];
    }
}
