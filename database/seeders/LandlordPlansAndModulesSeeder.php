<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class LandlordPlansAndModulesSeeder extends Seeder
{
    public function run(): void
    {
        $plan = Plan::on('landlord')->firstOrCreate(
            ['slug' => 'proplanner'],
            [
                'name' => 'Proplanner',
                'user_limit' => 15,
                'is_active' => true,
            ]
        );

        $module = Module::on('landlord')->firstOrCreate(
            ['slug' => 'kanban'],
            [
                'name' => 'Kanban',
                'is_active' => true,
            ]
        );

        Tenant::on('landlord')->whereNull('deleted_at')->each(function (Tenant $tenant) use ($plan, $module): void {
            if ($tenant->plan_id === null) {
                $tenant->update(['plan_id' => $plan->id]);
            }

            $tenant->modules()->syncWithoutDetaching([$module->id]);
        });

        $this->command->info("  Plan: {$plan->name} (user_limit={$plan->user_limit})");
        $this->command->info("  Module: {$module->name} (slug={$module->slug}) — associado a todos os tenants");
    }
}
