<?php

use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Modules\ModuleSlug;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    $this->actingAs(User::factory()->create());
});

test('landlord kanban templates route is forbidden when tenant does not have active kanban module', function () {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant Alfa',
        'slug' => 'alfa',
        'database' => 'tenant_alfa',
        'status' => 'active',
    ]);

    $response = $this->get(route('landlord.tenants.kanban.templates.index', ['tenant' => $tenant->id]));

    $response->assertForbidden();
});

test('tenant kanban route is forbidden when tenant does not have active kanban module', function () {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant Alfa',
        'slug' => 'alfa',
        'database' => 'tenant_alfa',
        'status' => 'active',
    ]);

    $tenant->domains()->create([
        'host' => 'alfa.'.config('app.landlord_domain'),
        'type' => 'subdomain',
        'is_primary' => true,
        'is_active' => true,
    ]);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'alfa.'.config('app.landlord_domain')])
        ->get(route('tenant.kanban.index', ['subdomain' => 'alfa'], false));

    $response->assertForbidden();
});

test('tenant kanban route is forbidden when kanban module is globally inactive', function () {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant Alfa',
        'slug' => 'alfa',
        'database' => 'tenant_alfa',
        'status' => 'active',
    ]);

    $tenant->domains()->create([
        'host' => 'alfa.'.config('app.landlord_domain'),
        'type' => 'subdomain',
        'is_primary' => true,
        'is_active' => true,
    ]);

    $kanban = Module::query()->create([
        'name' => 'Kanban',
        'slug' => ModuleSlug::KANBAN,
        'is_active' => false,
    ]);

    $tenant->modules()->attach($kanban->id);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'alfa.'.config('app.landlord_domain')])
        ->get(route('tenant.kanban.index', ['subdomain' => 'alfa'], false));

    $response->assertForbidden();
});
