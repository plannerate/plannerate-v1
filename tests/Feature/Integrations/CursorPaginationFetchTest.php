<?php

use App\Jobs\Integrations\FetchIntegrationPageJob;
use App\Jobs\Integrations\ProcessPageResponseJob;
use App\Models\IntegrationApi;
use App\Models\IntegrationImportRun;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

/*
 * Cobre as extensões do motor exigidas por APIs estilo RP Info:
 *   - paginação por cursor ({cursor} no path, sem last_page na resposta)
 *   - documento da loja no path ({store_document}) em vez da query
 *   - token em header customizado ("token: <jwt>") em vez de Authorization
 *   - erro lógico com HTTP 200 ({"response":{"status":"error"}})
 *   - items_path por path (endpoints diferentes, envelopes diferentes)
 */

beforeEach(function (): void {
    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

/**
 * @param  array<string, mixed>  $pathOverrides
 * @param  array<string, mixed>  $responseMeta
 * @param  array<string, mixed>  $configOverrides
 */
function makeCursorIntegration(
    string $slug,
    array $pathOverrides = [],
    array $responseMeta = [],
    array $configOverrides = [],
): TenantIntegration {
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
            'pagination_mode' => 'cursor',
            'store_document_field' => 'unidade',
            'paths' => [
                'products' => [
                    'target_table' => 'products',
                    'fallback_path' => '/v3.2/produtos/{cursor}/unidade/{store_document}/detalhado',
                    'cursor_item_path' => 'Codigo',
                    'cursor_initial' => 0,
                    'field_map' => [
                        ['target' => 'codigo_erp', 'source' => 'Codigo', 'transforms' => ['string', 'not_null']],
                    ],
                    ...$pathOverrides,
                ],
            ],
        ],
        'response' => [
            'items_path' => 'response.produtos',
            ...$responseMeta,
        ],
        'is_active' => true,
    ]);

    return TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => $api->id,
        'config' => [
            'connection' => ['base_url' => 'https://erp.cursor.test'],
            ...$configOverrides,
        ],
        'is_active' => true,
    ]);
}

/** @param array<int, array<string, mixed>> $produtos */
function cursorPage(array $produtos): array
{
    return ['response' => ['status' => 'ok', 'produtos' => $produtos]];
}

test('encadeia a próxima busca usando o id do último item', function (): void {
    Http::fake([
        'erp.cursor.test/*' => Http::response(cursorPage([
            ['Codigo' => 101],
            ['Codigo' => 268],
        ])),
    ]);
    Bus::fake([FetchIntegrationPageJob::class, ProcessPageResponseJob::class]);

    $integration = makeCursorIntegration('cursor-chain');

    (new FetchIntegrationPageJob(
        (string) $integration->id, 'products', 1,
        storeDocument: '00073351000122',
        cursor: '0',
    ))->handle();

    Bus::assertDispatched(
        FetchIntegrationPageJob::class,
        fn (FetchIntegrationPageJob $j): bool => $j->cursor === '268' && $j->page === 2,
    );
});

test('para a cadeia na página vazia', function (): void {
    Http::fake([
        'erp.cursor.test/*' => Http::response(cursorPage([])),
    ]);
    Bus::fake([FetchIntegrationPageJob::class, ProcessPageResponseJob::class]);

    $integration = makeCursorIntegration('cursor-end');

    (new FetchIntegrationPageJob(
        (string) $integration->id, 'products', 3,
        storeDocument: '00073351000122',
        cursor: '900',
    ))->handle();

    Bus::assertNotDispatched(FetchIntegrationPageJob::class);
});

test('para a cadeia quando o cursor não avança — guarda contra loop infinito', function (): void {
    Http::fake([
        'erp.cursor.test/*' => Http::response(cursorPage([['Codigo' => 500]])),
    ]);
    Bus::fake([FetchIntegrationPageJob::class, ProcessPageResponseJob::class]);

    $integration = makeCursorIntegration('cursor-stuck');

    (new FetchIntegrationPageJob(
        (string) $integration->id, 'products', 2,
        storeDocument: '00073351000122',
        cursor: '500',
    ))->handle();

    Bus::assertNotDispatched(FetchIntegrationPageJob::class);
});

test('uma página inteira rejeitada pelas validações ainda encadeia a próxima', function (): void {
    Http::fake([
        'erp.cursor.test/*' => Http::response(cursorPage([
            ['Codigo' => 700, 'Status' => 'CANCELADO'],
            ['Codigo' => 800, 'Status' => 'CANCELADO'],
        ])),
    ]);
    Bus::fake([FetchIntegrationPageJob::class, ProcessPageResponseJob::class]);

    $integration = makeCursorIntegration('cursor-rejected', [
        'validations' => [['type' => 'all_of', 'sources' => ['Status'], 'allowed_values' => ['NORMAL']]],
    ]);

    (new FetchIntegrationPageJob(
        (string) $integration->id, 'products', 1,
        storeDocument: '00073351000122',
        cursor: '0',
    ))->handle();

    Bus::assertNotDispatched(ProcessPageResponseJob::class);
    Bus::assertDispatched(
        FetchIntegrationPageJob::class,
        fn (FetchIntegrationPageJob $j): bool => $j->cursor === '800',
    );
});

test('resolve {cursor} e {store_document} no path e não repete o documento na query', function (): void {
    Http::fake([
        'erp.cursor.test/*' => Http::response(cursorPage([['Codigo' => 42]])),
    ]);
    Bus::fake([FetchIntegrationPageJob::class, ProcessPageResponseJob::class]);

    $integration = makeCursorIntegration('cursor-url');

    (new FetchIntegrationPageJob(
        (string) $integration->id, 'products', 1,
        storeDocument: '00073351000122',
        cursor: '268',
    ))->handle();

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://erp.cursor.test/v3.2/produtos/268/unidade/00073351000122/detalhado'
            && ! str_contains($request->url(), 'unidade=');
    });
});

test('no modo cursor não manda os parâmetros de paginação na query', function (): void {
    Http::fake([
        'erp.cursor.test/*' => Http::response(cursorPage([['Codigo' => 42]])),
    ]);
    Bus::fake([FetchIntegrationPageJob::class, ProcessPageResponseJob::class]);

    $integration = makeCursorIntegration('cursor-nopaging');

    (new FetchIntegrationPageJob(
        (string) $integration->id, 'products', 1,
        storeDocument: '00073351000122',
        cursor: '0',
    ))->handle();

    Http::assertSent(fn ($request): bool => ! str_contains($request->url(), 'pagina')
        && ! str_contains($request->url(), 'per_page'));
});

test('manda o token no header configurado em vez de Authorization', function (): void {
    Http::fake([
        'erp.cursor.test/auth' => Http::response(['response' => ['token' => 'jwt-abc']]),
        'erp.cursor.test/*' => Http::response(cursorPage([['Codigo' => 42]])),
    ]);
    Bus::fake([FetchIntegrationPageJob::class, ProcessPageResponseJob::class]);

    $integration = makeCursorIntegration('cursor-token', configOverrides: [
        'auth' => [
            'type' => 'bearer',
            'token_mode' => 'fetch',
            'token_header' => 'token',
            'credentials' => ['username' => '100077', 'password' => 'segredo'],
            'token_request' => [
                'method' => 'POST',
                'path' => 'auth',
                'username_field' => 'usuario',
                'password_field' => 'senha',
                'response_path' => 'response.token',
            ],
        ],
    ]);

    (new FetchIntegrationPageJob(
        (string) $integration->id, 'products', 1,
        storeDocument: '00073351000122',
        cursor: '0',
    ))->handle();

    Http::assertSent(function ($request): bool {
        if (! str_contains($request->url(), '/v3.2/produtos/')) {
            return false;
        }

        return $request->header('token') === ['jwt-abc'] && $request->header('Authorization') === [];
    });
});

test('erro lógico com HTTP 200 falha o job e não marca cobertura', function (): void {
    Http::fake([
        'erp.cursor.test/*' => Http::response([
            'response' => [
                'status' => 'error',
                'messages' => [['message' => "parâmetro 'documentos' é obrigatório"]],
            ],
        ]),
    ]);
    Bus::fake([FetchIntegrationPageJob::class, ProcessPageResponseJob::class]);

    $integration = makeCursorIntegration('cursor-logical-error', responseMeta: [
        'error_status_path' => 'response.status',
        'error_status_values' => ['error'],
        'error_message_path' => 'response.messages',
    ]);

    $run = IntegrationImportRun::startRun([
        'tenant_id' => (string) $integration->tenant_id,
        'integration_id' => (string) $integration->id,
        'path_key' => 'products',
        'store_id' => null,
        'mode' => 'cursor',
        'reference_date' => now()->toDateString(),
        'expected_units' => 1,
    ]);

    $job = new FetchIntegrationPageJob(
        (string) $integration->id, 'products', 1,
        storeDocument: '00073351000122',
        runId: (string) $run->id,
        cursor: '0',
    );

    expect(fn () => $job->handle())
        ->toThrow(RuntimeException::class, "parâmetro 'documentos' é obrigatório");

    expect((int) $run->fresh()->covered_units)->toBe(0);
    Bus::assertNotDispatched(FetchIntegrationPageJob::class);
});

test('items_path do path vence o global — endpoints com envelopes diferentes', function (): void {
    Http::fake([
        'erp.cursor.test/*' => Http::response([
            'response' => ['status' => 'ok', 'movimentos' => [['id' => 77]]],
        ]),
    ]);
    Bus::fake([FetchIntegrationPageJob::class, ProcessPageResponseJob::class]);

    $integration = makeCursorIntegration('cursor-items-path', [
        'items_path' => 'response.movimentos',
        'cursor_item_path' => 'id',
        'field_map' => [['target' => 'codigo_erp', 'source' => 'id', 'transforms' => ['string', 'not_null']]],
    ]);

    (new FetchIntegrationPageJob(
        (string) $integration->id, 'products', 1,
        storeDocument: '00073351000122',
        cursor: '0',
    ))->handle();

    Bus::assertDispatched(ProcessPageResponseJob::class);
    Bus::assertDispatched(
        FetchIntegrationPageJob::class,
        fn (FetchIntegrationPageJob $j): bool => $j->cursor === '77',
    );
});
