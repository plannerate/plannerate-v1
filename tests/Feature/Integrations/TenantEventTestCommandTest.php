<?php

use App\Events\Tenant\TenantIsolationCheckEvent;
use App\Models\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    config()->set('database.connections.landlord', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);

    Schema::connection('landlord')->dropIfExists('tenants');

    Schema::connection('landlord')->create('tenants', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->string('name');
        $table->string('slug')->unique();
        $table->string('database')->nullable();
        $table->string('status')->default('active');
        $table->timestamp('provisioned_at')->nullable();
        $table->text('provisioning_error')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
});

test('tenant test event command dispatches event with current tenant context', function (): void {
    Event::fake([TenantIsolationCheckEvent::class]);

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Diagnostico',
        'slug' => 'tenant-diagnostico',
        'database' => 'tenant_diagnostico',
        'status' => 'active',
    ]));

    $this->artisan(sprintf('tenant:test-event %s --resource=tenant_split_check', (string) $tenant->id))
        ->expectsOutputToContain('Evento de teste disparado com sucesso para tenant')
        ->assertExitCode(0);

    Event::assertDispatched(TenantIsolationCheckEvent::class, function (TenantIsolationCheckEvent $event) use ($tenant): bool {
        return $event->tenantId === (string) $tenant->id
            && $event->currentTenantId === (string) $tenant->id
            && $event->tenantSlug === 'tenant-diagnostico'
            && $event->resource === 'tenant_split_check'
            && $event->status === 'ok';
    });
});

test('tenant test event command fails when tenant does not exist', function (): void {
    Event::fake([TenantIsolationCheckEvent::class]);

    $missingTenantId = (string) str()->ulid();

    $this->artisan(sprintf('tenant:test-event %s', $missingTenantId))
        ->expectsOutputToContain('Tenant nao encontrado: '.$missingTenantId)
        ->assertExitCode(1);

    Event::assertNotDispatched(TenantIsolationCheckEvent::class);
});

test('tenant test event command allows selecting tenant when id is omitted', function (): void {
    Event::fake([TenantIsolationCheckEvent::class]);

    $tenantA = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Alfa',
        'slug' => 'tenant-alfa',
        'database' => 'tenant_alfa',
        'status' => 'active',
    ]));

    $tenantB = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Beta',
        'slug' => 'tenant-beta',
        'database' => 'tenant_beta',
        'status' => 'active',
    ]));

    $selectedOption = sprintf('%s [%s] (%s)', 'Tenant Beta', 'tenant-beta', (string) $tenantB->id);

    $this->artisan('tenant:test-event --resource=tenant_split_check')
        ->expectsChoice(
            'Selecione o tenant alvo',
            $selectedOption,
            [
                sprintf('%s [%s] (%s)', 'Tenant Alfa', 'tenant-alfa', (string) $tenantA->id),
                $selectedOption,
            ],
        )
        ->expectsOutputToContain('Evento de teste disparado com sucesso para tenant')
        ->assertExitCode(0);

    Event::assertDispatched(TenantIsolationCheckEvent::class, function (TenantIsolationCheckEvent $event) use ($tenantB): bool {
        return $event->tenantId === (string) $tenantB->id
            && $event->currentTenantId === (string) $tenantB->id
            && $event->tenantSlug === 'tenant-beta'
            && $event->resource === 'tenant_split_check'
            && $event->status === 'ok';
    });
});
