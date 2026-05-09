<?php

use App\Models\Tenant;
use App\Models\TenantIntegration;
use Illuminate\Support\Facades\Artisan;

test('daily imports command prints active integrations', function (): void {
    config([
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
    ]);

    $activeTenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Ativo',
        'slug' => 'tenant-ativo',
        'database' => 'tenant_active',
        'status' => 'active',
    ]));

    $inactiveTenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Inativo',
        'slug' => 'tenant-inativo',
        'database' => 'tenant_inactive',
        'status' => 'active',
    ]));

    TenantIntegration::query()->create([
        'tenant_id' => $activeTenant->id,
        'integration_type' => 'sysmo',
        'identifier' => 'principal',
        'config' => [],
        'is_active' => true,
    ]);

    TenantIntegration::query()->create([
        'tenant_id' => $inactiveTenant->id,
        'integration_type' => 'sysmo',
        'identifier' => 'desativada',
        'config' => [],
        'is_active' => false,
    ]);

    Artisan::call('integrations:daily-imports');

    $output = Artisan::output();

    expect($output)
        ->toContain('Integrações ativas encontradas para busca diária: 1')
        ->toContain('Tenant Ativo')
        ->toContain('principal')
        ->not->toContain('Tenant Inativo')
        ->not->toContain('desativada');
});

test('daily imports command warns when there are no active integrations', function (): void {
    Artisan::call('integrations:daily-imports');

    expect(Artisan::output())->toContain('Nenhuma integração ativa encontrada para a busca diária.');
});
