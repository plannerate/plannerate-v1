<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'database' => 'tenant_'.Str::lower(Str::random(10)),
            'status' => fake()->randomElement(['active', 'inactive', 'suspended']),
            'plan_id' => Plan::factory(),
            'provisioned_at' => now(),
            'provisioning_error' => null,
        ];
    }
}
