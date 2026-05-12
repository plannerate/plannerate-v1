<?php

use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Services\Integrations\Importers\GenericIntegrationImporter;
use App\Services\Integrations\Importers\IntegrationImporter;
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

    Schema::connection('tenant')->create('stores', function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->string('tenant_id')->nullable();
        $table->string('name')->nullable();
        $table->string('document')->nullable();
        $table->string('status')->default('draft');
        $table->timestamp('deleted_at')->nullable();
    });
});

afterEach(function (): void {
    Schema::connection('tenant')->dropIfExists('stores');
    DB::purge('tenant');
});

test('store scoped imports only use published stores with documents', function (): void {
    $tenant = Mockery::mock(Tenant::class)->makePartial();
    $tenant->forceFill(['id' => '01jts31n2rpz1tyy4n6xv4qdn0']);
    $tenant
        ->shouldReceive('execute')
        ->once()
        ->andReturnUsing(fn (callable $callback): mixed => $callback());

    DB::connection('tenant')->table('stores')->insert([
        [
            'id' => '01jts31n2rpz1tyy4n6xv4qdp1',
            'tenant_id' => $tenant->id,
            'name' => 'Loja Publicada',
            'document' => '11.111.111/0001-11',
            'status' => 'published',
        ],
        [
            'id' => '01jts31n2rpz1tyy4n6xv4qdp2',
            'tenant_id' => $tenant->id,
            'name' => 'Loja Rascunho',
            'document' => '22.222.222/0001-22',
            'status' => 'draft',
        ],
        [
            'id' => '01jts31n2rpz1tyy4n6xv4qdp3',
            'tenant_id' => $tenant->id,
            'name' => 'Loja Sem Documento',
            'document' => null,
            'status' => 'published',
        ],
    ]);

    $integration = new TenantIntegration([
        'tenant_id' => $tenant->id,
        'config' => [
            'paths' => [
                'products' => [
                    'include_store_in_id' => true,
                ],
            ],
        ],
    ]);
    $integration->setRelation('tenant', $tenant);

    $genericImporter = Mockery::mock(GenericIntegrationImporter::class);
    $genericImporter
        ->shouldReceive('importResource')
        ->once()
        ->withArgs(function (mixed $receivedIntegration, string $resource, string $targetTable, Store $store) use ($integration): bool {
            $receivedModel = $receivedIntegration instanceof TenantIntegration
                ? $receivedIntegration
                : ($receivedIntegration->integration ?? null);

            return $receivedModel instanceof TenantIntegration
                && $receivedModel === $integration
                && $resource === 'products'
                && $targetTable === 'products'
                && $store->id === '01jts31n2rpz1tyy4n6xv4qdp1'
                && $store->document === '11.111.111/0001-11';
        });

    (new IntegrationImporter($genericImporter))->importResource($integration, 'products');
});
