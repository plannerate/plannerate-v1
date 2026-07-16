<?php

use App\Jobs\Integrations\FetchIntegrationPageJob;
use App\Jobs\Integrations\ProcessPageResponseJob;
use App\Models\IntegrationApi;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Services\Integrations\Support\DeterministicIdGenerator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/*
 * E2E do pipeline de importação: FetchIntegrationPageJob (busca + field_map +
 * id determinístico + grava JSON) → ProcessPageResponseJob (lê JSON + reconcilia
 * + upsert no tenant).
 *
 * Isolation-safe: `switch_tenant_tasks => []` torna $tenant->execute() um
 * passthrough — o tenant vira current mas o banco NÃO troca, então o callback
 * do TenantRecordPersister roda na conexão `tenant` in-memory onde criamos as
 * tabelas. Nunca trocamos database.default nem chamamos DB::purge.
 */
beforeEach(function (): void {
    config(['multitenancy.switch_tenant_tasks' => []]);

    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    if (! DB::connection('tenant')->getSchemaBuilder()->hasTable('products')) {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/2026_04_22_200100_create_products_table.php',
            '--realpath' => false,
            '--force' => true,
            '--no-interaction' => true,
        ]);
    }

    if (! DB::connection('tenant')->getSchemaBuilder()->hasTable('sales')) {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/2026_04_23_250000_create_sales_table.php',
            '--realpath' => false,
            '--force' => true,
            '--no-interaction' => true,
        ]);
    }
});

/**
 * @return array<string, mixed>
 */
function sysmoProductsPathConfig(): array
{
    return [
        'fallback_path' => '/produtos',
        'target_table' => 'products',
        'unique_by' => ['ean'],
        'id_prefix' => 'P1',
        'field_map' => [
            ['target' => 'name', 'source' => 'descricao'],
            ['target' => 'codigo_erp', 'source' => 'produto'],
            ['target' => 'ean', 'source' => 'ean'],
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function sysmoSalesPathConfig(): array
{
    return [
        'fallback_path' => '/vendas',
        'target_table' => 'sales',
        'unique_by' => ['codigo_erp', 'sale_date', 'promotion'],
        'id_prefix' => 'S1',
        'include_store_in_id' => true,
        'field_map' => [
            ['target' => 'codigo_erp', 'source' => 'produto'],
            ['target' => 'sale_date', 'source' => 'data_venda'],
            ['target' => 'promotion', 'source' => 'promocao'],
            ['target' => 'total_sale_quantity', 'source' => 'quantidade'],
            ['target' => 'total_sale_value', 'source' => 'valor_liquido'],
            ['target' => 'acquisition_cost', 'source' => 'custo_aquisicao'],
            ['target' => 'margem_contribuicao', 'source' => 'valor_liquido - valor_impostos - custo_medio_loja'],
        ],
    ];
}

function makeSysmoIntegration(string $slug): TenantIntegration
{
    $tenant = Tenant::withoutEvents(function () use ($slug): Tenant {
        return Tenant::query()->create([
            'name' => strtoupper($slug),
            'slug' => $slug,
            'database' => (string) config('database.connections.landlord.database').'_'.$slug,
            'status' => 'active',
        ]);
    });

    $api = IntegrationApi::query()->create([
        'name' => strtoupper($slug),
        'slug' => $slug,
        'requests' => [
            'method' => 'GET',
            'paths' => [
                'products' => sysmoProductsPathConfig(),
                'sales' => sysmoSalesPathConfig(),
            ],
        ],
        'response' => [
            'items_path' => 'dados',
            'pagination' => ['last_page_path' => 'meta.last_page'],
        ],
        'is_active' => true,
    ]);

    return TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => $api->id,
        'config' => ['connection' => ['base_url' => 'https://erp.sysmo.test']],
        'is_active' => true,
    ]);
}

/**
 * Dirige a corrente de cabo a rabo: roda o fetch (que despacha o process via
 * Bus fake), captura o job despachado e roda o handle() dele manualmente com o
 * JSON intermediário que sobrevive no Storage::fake.
 */
function runImportPipeline(TenantIntegration $integration, string $pathKey): void
{
    (new FetchIntegrationPageJob((string) $integration->id, $pathKey, 1))->handle();

    foreach (Bus::dispatched(ProcessPageResponseJob::class) as $processJob) {
        $processJob->handle();
    }
}

// ─── Cenário 1: produtos happy path ──────────────────────────────────────────

test('produtos — importa a página inteira com id determinístico e tenant_id', function (): void {
    Storage::fake('local');
    Bus::fake([ProcessPageResponseJob::class]);

    Http::fake([
        'erp.sysmo.test/produtos*' => Http::response([
            'dados' => [
                ['produto' => 'ERP-1', 'descricao' => 'Arroz 5kg', 'ean' => '7891000100103'],
                ['produto' => 'ERP-2', 'descricao' => 'Feijão 1kg', 'ean' => '7891000200104'],
            ],
            'meta' => ['last_page' => 1],
        ]),
    ]);

    $integration = makeSysmoIntegration('e2e-products');
    $tenantId = (string) $integration->tenant_id;
    $integrationId = (string) $integration->id;

    runImportPipeline($integration, 'products');

    $rows = DB::connection('tenant')->table('products')->orderBy('codigo_erp')->get();

    expect($rows)->toHaveCount(2);

    $arroz = $rows->firstWhere('codigo_erp', 'ERP-1');
    expect($arroz->ean)->toBe('7891000100103')
        ->and($arroz->name)->toBe('Arroz 5kg')
        ->and($arroz->tenant_id)->toBe($tenantId);

    // ID determinístico: derivado de tenant|integration|ean (unique_by = ['ean']).
    $expectedId = (new DeterministicIdGenerator)->fromRecord(
        $tenantId,
        $integrationId,
        ['ean' => '7891000100103'],
        sysmoProductsPathConfig(),
        null,
    );

    expect($arroz->id)->toBe($expectedId)
        ->and($arroz->id)->toMatch('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/'); // ULID válido
});

// ─── Cenário 2: vendas happy path + margem calculada + dedupe por id ─────────

test('vendas — persiste a margem calculada e deduplica a chave natural repetida', function (): void {
    Storage::fake('local');
    Bus::fake([ProcessPageResponseJob::class]);

    $sale = [
        'produto' => 'ERP-1',
        'data_venda' => '2026-07-10',
        'promocao' => 'N',
        'quantidade' => 3,
        'valor_liquido' => 23.99,
        'valor_impostos' => 2.88,
        'custo_medio_loja' => 15.8607,
        'custo_aquisicao' => 14.50,
    ];

    Http::fake([
        // Dois registros com a MESMA chave natural (codigo_erp+sale_date+promotion)
        // → mesmo id determinístico → deve colapsar em uma única linha.
        'erp.sysmo.test/vendas*' => Http::response([
            'dados' => [$sale, $sale],
            'meta' => ['last_page' => 1],
        ]),
    ]);

    $integration = makeSysmoIntegration('e2e-sales');

    runImportPipeline($integration, 'sales');

    $rows = DB::connection('tenant')->table('sales')->get();

    expect($rows)->toHaveCount(1);

    $row = $rows->first();

    // Margem = valor_liquido - valor_impostos - custo_medio_loja
    //        = 23.99 - 2.88 - 15.8607 = 5.2493
    expect((float) $row->margem_contribuicao)->toEqualWithDelta(5.2493, 0.0001)
        ->and((float) $row->total_sale_value)->toEqualWithDelta(23.99, 0.001)
        ->and((float) $row->total_sale_quantity)->toEqualWithDelta(3.0, 0.001)
        ->and($row->codigo_erp)->toBe('ERP-1')
        ->and($row->promotion)->toBe('N')
        ->and($row->tenant_id)->toBe((string) $integration->tenant_id);
});

// ─── Cenário 3: reconciliação de EAN soft-deleted ───────────────────────────

test('produtos — reusa e restaura a linha soft-deleted quando o mesmo EAN volta no feed', function (): void {
    Storage::fake('local');
    Bus::fake([ProcessPageResponseJob::class]);

    $integration = makeSysmoIntegration('e2e-reconcile');
    $tenantId = (string) $integration->tenant_id;

    // Produto soft-deleted já existente com um id ARBITRÁRIO (diferente do
    // determinístico que o feed vai gerar) para o mesmo EAN.
    $existingDeletedId = (string) str()->ulid();
    DB::connection('tenant')->table('products')->insert([
        'id' => $existingDeletedId,
        'tenant_id' => $tenantId,
        'name' => 'Produto Antigo',
        'slug' => 'produto-antigo-reconcile',
        'ean' => '7891000300105',
        'codigo_erp' => 'ERP-OLD',
        'status' => 'published',
        'created_at' => now(),
        'updated_at' => now(),
        'deleted_at' => now(),
    ]);

    Http::fake([
        'erp.sysmo.test/produtos*' => Http::response([
            'dados' => [
                ['produto' => 'ERP-NEW', 'descricao' => 'Produto Novo', 'ean' => '7891000300105'],
            ],
            'meta' => ['last_page' => 1],
        ]),
    ]);

    runImportPipeline($integration, 'products');

    // Uma única linha para aquele EAN: a antiga reusada + restaurada, não bifurcada.
    $rows = DB::connection('tenant')->table('products')->where('ean', '7891000300105')->get();

    expect($rows)->toHaveCount(1);

    $row = $rows->first();

    expect($row->id)->toBe($existingDeletedId)   // id da linha antiga reaproveitado
        ->and($row->deleted_at)->toBeNull()      // restaurada
        ->and($row->codigo_erp)->toBe('ERP-NEW') // atualizada com os dados do feed
        ->and($row->name)->toBe('Produto Novo');
});

// ─── Cenário 4: dedup por id na mesma página ─────────────────────────────────

test('produtos — dois registros do mesmo EAN na mesma página viram uma linha só', function (): void {
    Storage::fake('local');
    Bus::fake([ProcessPageResponseJob::class]);

    Http::fake([
        'erp.sysmo.test/produtos*' => Http::response([
            'dados' => [
                ['produto' => 'ERP-A', 'descricao' => 'Versão A', 'ean' => '7891000400106'],
                ['produto' => 'ERP-B', 'descricao' => 'Versão B', 'ean' => '7891000400106'],
            ],
            'meta' => ['last_page' => 1],
        ]),
    ]);

    $integration = makeSysmoIntegration('e2e-dedupe');

    runImportPipeline($integration, 'products');

    $rows = DB::connection('tenant')->table('products')->where('ean', '7891000400106')->get();

    // Mesmo EAN → mesmo id determinístico → deduplicado para uma linha (o último vence).
    expect($rows)->toHaveCount(1)
        ->and($rows->first()->codigo_erp)->toBe('ERP-B');
});
