<?php

use Callcocam\LaravelIntegrations\Contracts\StoresProvider;
use Callcocam\LaravelIntegrations\Models\Concerns\HasIntegration;
use Callcocam\LaravelIntegrations\Services\Support\IntegrationModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/*
 * Contrato entre a aplicação e o pacote `callcocam/laravel-integrations`.
 *
 * O motor é genérico, mas escreve no schema DESTA aplicação, guiado por config. Isso
 * cria uma classe de erro que nenhum dos dois lados pega sozinho: o pacote tem fixtures
 * próprias do schema do tenant e passa verde contra elas; a aplicação evolui o schema
 * real e não sabe que alguém depende dele. O drift só aparece no ciclo diário, gravando
 * — ou deixando de gravar — dado de cliente.
 *
 * Estes testes ficam do lado da aplicação de propósito: só aqui existem o schema real,
 * as policies, o middleware e o RBAC.
 */

/*
 * Uma única asserção de schema, de propósito.
 *
 * O schema do tenant vive numa conexão que o RefreshDatabase não transaciona: o sqlite
 * `:memory:` do tenant não sobrevive de um teste para o outro, e o `migrate` seguinte é
 * no-op porque o bookkeeping ficou na conexão default. Espalhar as verificações por
 * vários testes daria falso vermelho a partir do segundo. Como bônus, uma asserção só
 * reporta TODOS os desvios de contrato de uma vez, em vez do primeiro.
 */
test('o schema real do tenant satisfaz o contrato declarado em integrations.*', function (): void {
    $problems = [];

    /** @var array<string, string> $tables */
    $tables = config('integrations.tables');
    expect($tables)->not->toBeEmpty();

    foreach ($tables as $role => $table) {
        if (! Schema::connection('tenant')->hasTable($table)) {
            $problems[] = "tabela ausente: {$role} => {$table}";
        }
    }

    // Coluna oferecida pelo field_map que não existe estoura o upsert no meio do import.
    /** @var array<string, array{label: string, columns: list<string>}> $fieldMapTables */
    $fieldMapTables = config('integrations.field_map_tables');
    expect($fieldMapTables)->not->toBeEmpty();

    foreach ($fieldMapTables as $role => $definition) {
        $table = (string) config("integrations.tables.{$role}", $role);

        if (! Schema::connection('tenant')->hasTable($table)) {
            continue;
        }

        $existing = Schema::connection('tenant')->getColumnListing($table);

        foreach ($definition['columns'] ?? [] as $column) {
            if (! in_array($column, $existing, true)) {
                $problems[] = "field_map aponta para coluna inexistente: {$table}.{$column}";
            }
        }
    }

    /** @var array<string, array{columns: list<string>, soft_deletes?: bool}> $naturalKeys */
    $naturalKeys = config('integrations.natural_keys');
    expect($naturalKeys)->not->toBeEmpty();

    foreach ($naturalKeys as $role => $definition) {
        $table = (string) config("integrations.tables.{$role}", $role);

        if (! Schema::connection('tenant')->hasTable($table)) {
            continue;
        }

        $existing = Schema::connection('tenant')->getColumnListing($table);

        foreach ($definition['columns'] as $column) {
            if (! in_array($column, $existing, true)) {
                $problems[] = "natural_key aponta para coluna inexistente: {$table}.{$column}";
            }
        }

        // `soft_deletes` mentindo é destrutivo: com false numa tabela que tem deleted_at,
        // o reconciler insere linha nova e o índice único parcial estoura duplicate key.
        $declared = (bool) ($definition['soft_deletes'] ?? false);
        $actual = in_array('deleted_at', $existing, true);

        if ($declared !== $actual) {
            $problems[] = sprintf(
                '%s: soft_deletes declarado %s, tabela %s deleted_at',
                $table,
                $declared ? 'true' : 'false',
                $actual ? 'tem' : 'não tem',
            );
        }
    }

    // A coluna do CNPJ enviado ao ERP precisa existir de fato.
    $storesTable = (string) config('integrations.tables.stores');
    $documentColumn = (string) config('integrations.store_document_column');

    if (Schema::connection('tenant')->hasTable($storesTable)
        && ! in_array($documentColumn, Schema::connection('tenant')->getColumnListing($storesTable), true)) {
        $problems[] = "store_document_column inexistente: {$storesTable}.{$documentColumn}";
    }

    expect($problems)->toBe([]);
});

test('os models declarados em integrations.models cumprem o contrato do motor', function (): void {
    expect(class_exists(IntegrationModels::product()))->toBeTrue()
        ->and(class_exists(IntegrationModels::store()))->toBeTrue()
        ->and(class_exists(IntegrationModels::user()))->toBeTrue()
        ->and(class_exists(IntegrationModels::eanReference()))->toBeTrue();

    // O motor chama estaticamente ao sincronizar dimensões da base de EANs.
    expect(method_exists(IntegrationModels::eanReference(), 'normalizeEan'))->toBeTrue();

    // `store_scope` é o critério de "loja importável"; scope inexistente derruba a descoberta.
    $storeScope = (string) config('integrations.store_scope');

    if ($storeScope !== '') {
        expect(method_exists(IntegrationModels::store(), 'scope'.ucfirst($storeScope)))->toBeTrue();
    }
});

test('o model de tenant expõe a relação que o motor consome', function (): void {
    $tenantModel = IntegrationModels::tenant();

    expect(in_array(HasIntegration::class, class_uses_recursive($tenantModel), true))->toBeTrue()
        ->and(method_exists($tenantModel, 'integration'))->toBeTrue();
});

test('o StoresProvider está bindado e resolve', function (): void {
    expect(app(StoresProvider::class))->toBeInstanceOf(StoresProvider::class);
});

test('as rotas landlord do motor mantêm nome, URI e middleware da aplicação', function (): void {
    // Nome e URI são contrato com o frontend (Wayfinder deriva do nome da rota).
    // O middleware é contrato de segurança: sem SetPermissionTeamContext a autorização
    // decide fora do time do tenant.
    $expected = [
        'landlord.integration-apis.index' => 'integration-apis',
        'landlord.integration-apis.create' => 'integration-apis/create',
        'landlord.integration-apis.store' => 'integration-apis',
        'landlord.integration-apis.edit' => 'integration-apis/{integration_api}/edit',
        'landlord.integration-apis.update' => 'integration-apis/{integration_api}',
        'landlord.integration-apis.destroy' => 'integration-apis/{integration_api}',
        'landlord.integration-apis.restore' => 'integration-apis/{integration_api}/restore',
        'landlord.integration-apis.export' => 'integration-apis/export',
        'landlord.integration-apis.import' => 'integration-apis/import',
        'landlord.tenants.integration.edit' => 'tenants/{tenant}/integration',
        'landlord.tenants.integration.update' => 'tenants/{tenant}/integration',
        'landlord.tenants.integration.destroy' => 'tenants/{tenant}/integration',
        'landlord.tenants.integration.test-connection' => 'tenants/{tenant}/integration/test-connection',
        'landlord.tenants.integration.toggle-status' => 'tenants/{tenant}/integration/toggle-status',
        'landlord.tenants.integration.run-import' => 'tenants/{tenant}/integration/run-import',
        'landlord.tenants.integration.run-post-import' => 'tenants/{tenant}/integration/run-post-import',
    ];

    $problems = [];
    $requiredMiddleware = config('integrations.routes.middleware');

    foreach ($expected as $name => $uri) {
        $route = Route::getRoutes()->getByName($name);

        if ($route === null) {
            $problems[] = "rota ausente: {$name}";

            continue;
        }

        if ($route->uri() !== $uri) {
            $problems[] = "{$name}: URI {$route->uri()}, esperada {$uri}";
        }

        foreach ($requiredMiddleware as $middleware) {
            if (! in_array($middleware, $route->gatherMiddleware(), true)) {
                $problems[] = "{$name}: sem middleware {$middleware}";
            }
        }
    }

    expect($problems)->toBe([]);
});

test('os comandos do ciclo diário estão registrados', function (): void {
    // routes/console.php e o Horizon referenciam estes comandos por assinatura; se o
    // pacote parar de registrá-los, o agendamento falha calado.
    $expected = [
        'integration:run',
        'sync:post-import',
        'integration:health',
        'integration:status',
        'imports:prune',
        'sync:cleanup',
        'sync:link-sales',
        'sync:products-from-ean-references',
        'sync:layers-product-ids-by-ean',
        'monthly-sales:recalculate',
        'integrations:migrate',
    ];

    $registered = array_keys(Artisan::all());

    expect(array_values(array_diff($expected, $registered)))->toBe([]);
});
