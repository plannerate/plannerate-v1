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

        // Limites default por perfil administrativo (plan_items "user_limit:{system_name}").
        // Em branco/ausente = ilimitado; aqui damos um teto de exemplo para cada perfil.
        $roleLimits = [
            'kanban-aprovacao-da-area-de-gc' => 2,
            'kanban-revisao-de-dimensoes' => 3,
            'kanban-revisao-de-imagens' => 3,
            'kanban-revisao-periodica' => 2,
        ];

        foreach ($roleLimits as $systemName => $limit) {
            $plan->items()->updateOrCreate(
                ['key' => 'user_limit:'.$systemName],
                [
                    'label' => 'Limite de usuários: '.$systemName,
                    'value' => (string) $limit,
                    'type' => 'integer',
                    'is_active' => true,
                ],
            );
        }

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

        $imageBank = Module::on('landlord')->firstOrCreate(
            ['slug' => 'image-bank'],
            [
                'name' => 'Banco de Imagens',
                'is_active' => true,
            ]
        );

        $this->command->info("  Plan: {$plan->name} (user_limit={$plan->user_limit})");
        $this->command->info("  Module: {$module->name} (slug={$module->slug}) — associado a todos os tenants");
        $this->command->info("  Module: {$imageBank->name} (slug={$imageBank->slug}) — disponível para ativação por tenant");
    }
}
