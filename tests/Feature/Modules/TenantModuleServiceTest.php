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

test('tenant module service resolves legacy pt-br module slugs to the canonical slug', function () {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant Alfa',
        'slug' => 'tenant-alfa',
        'database' => 'tenant_alfa',
        'status' => 'active',
    ]);

    $template = Module::query()->create([
        'name' => 'Planograma template',
        'slug' => 'planograma-template',
        'is_active' => true,
    ]);

    $tenant->modules()->attach($template->id);

    $service = app(TenantModuleService::class);

    expect($service->tenantHasActiveModule($tenant, ModuleSlug::PLANOGRAM_TEMPLATE))->toBeTrue();
    expect($service->tenantHasActiveModule($tenant, 'planograma-template'))->toBeTrue();
    expect($service->tenantActiveModuleSlugs($tenant))->toBe([ModuleSlug::PLANOGRAM_TEMPLATE]);

    $tenant->load('modules');

    expect($service->tenantHasActiveModule($tenant, ModuleSlug::PLANOGRAM_TEMPLATE))->toBeTrue();
    expect($service->tenantActiveModuleSlugs($tenant))->toBe([ModuleSlug::PLANOGRAM_TEMPLATE]);

    expect(Tenant::query()->whereHasActiveModule(ModuleSlug::PLANOGRAM_TEMPLATE)->pluck('id')->all())
        ->toBe([$tenant->id]);
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
