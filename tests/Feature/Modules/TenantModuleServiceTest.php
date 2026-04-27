<?php

use App\Models\Module;
use App\Models\Tenant;
use App\Support\Modules\ModuleSlug;
use App\Support\Modules\TenantModuleService;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

test('tenant module service returns true when tenant has active module', function () {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant Alfa',
        'slug' => 'tenant-alfa',
        'database' => 'tenant_alfa',
        'status' => 'active',
    ]);

    $kanban = Module::query()->create([
        'name' => 'Kanban',
        'slug' => ModuleSlug::KANBAN,
        'is_active' => true,
    ]);

    $tenant->modules()->attach($kanban->id);

    $service = app(TenantModuleService::class);

    expect($service->tenantHasActiveModule($tenant, ModuleSlug::KANBAN))->toBeTrue();
    expect($service->tenantActiveModuleSlugs($tenant))->toContain(ModuleSlug::KANBAN);
});

test('tenant module service returns false when module is globally inactive', function () {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant Alfa',
        'slug' => 'tenant-alfa',
        'database' => 'tenant_alfa',
        'status' => 'active',
    ]);

    $kanban = Module::query()->create([
        'name' => 'Kanban',
        'slug' => ModuleSlug::KANBAN,
        'is_active' => false,
    ]);

    $tenant->modules()->attach($kanban->id);

    $service = app(TenantModuleService::class);

    expect($service->tenantHasActiveModule($tenant, ModuleSlug::KANBAN))->toBeFalse();
    expect($service->tenantActiveModuleSlugs($tenant))->not->toContain(ModuleSlug::KANBAN);
});
