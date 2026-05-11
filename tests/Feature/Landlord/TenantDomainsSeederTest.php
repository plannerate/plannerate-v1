<?php

use App\Jobs\ProvisionTenantDatabaseJob;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\Modules\ModuleSlug;
use Database\Seeders\TenantDomainsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    config([
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        'multitenancy.switch_tenant_tasks' => [],
    ]);
    Queue::fake([ProvisionTenantDatabaseJob::class]);

    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

test('tenant domains seeder provisions kanban plan and module for configured tenants', function (): void {
    Artisan::call('db:seed', [
        '--class' => TenantDomainsSeeder::class,
        '--force' => true,
        '--no-interaction' => true,
    ]);

    Artisan::call('db:seed', [
        '--class' => TenantDomainsSeeder::class,
        '--force' => true,
        '--no-interaction' => true,
    ]);

    $plan = Plan::query()->where('slug', 'plano-kanban')->firstOrFail();
    $module = Module::query()->where('slug', ModuleSlug::KANBAN)->firstOrFail();

    expect($plan->name)->toBe('Plano Kanban')
        ->and($plan->is_active)->toBeTrue()
        ->and($module->name)->toBe('Kanban')
        ->and($module->is_active)->toBeTrue();

    $tenants = Tenant::query()
        ->with('modules')
        ->whereIn('slug', ['bruda', 'franciosi'])
        ->orderBy('slug')
        ->get()
        ->keyBy('slug');

    expect($tenants)->toHaveCount(2);

    expect($tenants['bruda']->modules->pluck('slug'))->toContain(ModuleSlug::KANBAN)
        ->and($tenants['franciosi']->modules->pluck('slug'))->toContain(ModuleSlug::KANBAN);
});
