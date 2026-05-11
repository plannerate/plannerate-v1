<?php

use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Services\Integrations\Support\PersistImportedResourceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    config()->set('database.connections.tenant', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    config()->set('multitenancy.tenant_database_connection_name', 'tenant');

    DB::purge('tenant');

    Schema::connection('tenant')->create('widgets', function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->string('tenant_id');
        $table->string('external_id');
        $table->string('name')->nullable();
        $table->integer('quantity')->nullable();
        $table->timestamps();
        $table->softDeletes();
        $table->unique(['tenant_id', 'external_id']);
    });
});

afterEach(function (): void {
    Schema::connection('tenant')->dropIfExists('widgets');
    DB::purge('tenant');
});

test('generic resource persistence upserts by configured unique columns', function (): void {
    $tenant = Mockery::mock(Tenant::class)->makePartial();
    $tenant->forceFill(['id' => '01jts31n2rpz1tyy4n6xv4qdn0']);
    $tenant
        ->shouldReceive('execute')
        ->twice()
        ->andReturnUsing(fn (callable $callback): mixed => $callback());

    $integration = new TenantIntegration([
        'id' => '01k-resource-persist-test',
        'tenant_id' => $tenant->id,
        'integration_type' => 'custom-api',
        'config' => [
            'requests' => [
                'paths' => [
                    'widgets' => [
                        'target_table' => 'widgets',
                        'unique_by' => ['external_id'],
                        'field_map' => [
                            ['target' => 'external_id', 'source' => 'codigo'],
                            ['target' => 'name', 'source' => 'nome'],
                            ['target' => 'quantity', 'source' => 'quantidade'],
                        ],
                    ],
                ],
            ],
        ],
    ]);
    $integration->setRelation('tenant', $tenant);

    $service = app(PersistImportedResourceService::class);
    $service->persist($integration, 'custom-api', 'widgets', 'widgets', [
        ['codigo' => 'W-1', 'nome' => 'Primeiro', 'quantidade' => 2],
    ]);
    $service->persist($integration, 'custom-api', 'widgets', 'widgets', [
        ['codigo' => 'W-1', 'nome' => 'Atualizado', 'quantidade' => 5],
    ]);

    $rows = DB::connection('tenant')->table('widgets')->get();

    expect($rows)->toHaveCount(1)
        ->and((string) $rows->first()->tenant_id)->toBe((string) $tenant->id)
        ->and($rows->first()->external_id)->toBe('W-1')
        ->and($rows->first()->name)->toBe('Atualizado')
        ->and((int) $rows->first()->quantity)->toBe(5);
});

test('generic resource persistence skips writes without unique_by', function (): void {
    $tenant = Mockery::mock(Tenant::class)->makePartial();
    $tenant->forceFill(['id' => '01jts31n2rpz1tyy4n6xv4qdn1']);
    $tenant
        ->shouldReceive('execute')
        ->once()
        ->andReturnUsing(fn (callable $callback): mixed => $callback());

    $integration = new TenantIntegration([
        'id' => '01k-resource-no-unique-test',
        'tenant_id' => $tenant->id,
        'integration_type' => 'custom-api',
        'config' => [
            'requests' => [
                'paths' => [
                    'widgets' => [
                        'target_table' => 'widgets',
                        'field_map' => [
                            ['target' => 'external_id', 'source' => 'codigo'],
                        ],
                    ],
                ],
            ],
        ],
    ]);
    $integration->setRelation('tenant', $tenant);

    app(PersistImportedResourceService::class)->persist($integration, 'custom-api', 'widgets', 'widgets', [
        ['codigo' => 'W-2'],
    ]);

    expect(DB::connection('tenant')->table('widgets')->count())->toBe(0);
});
