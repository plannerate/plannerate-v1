<?php

use App\Jobs\Imports\ImportCategoriesFromSpreadsheetJob;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $landlordUsesSqlite = (string) config('database.connections.landlord.driver') === 'sqlite';

    if ($landlordUsesSqlite) {
        $this->markTestSkipped(
            'Import/export HTTP de categorias: landlord em SQLite (:memory:) + migrate:fresh no mesmo ciclo que RefreshDatabase quebra estes asserts; rodar em Sail/MySQL ou ver tests/Unit/Services/CategoryHierarchyImportServiceTest.php.'
        );
    }

    config()->set('permission.rbac_enabled', true);

    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    Artisan::call('migrate:fresh', [
        '--path' => 'database/migrations',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    Artisan::call('db:seed', [
        '--class' => LandlordRbacSeeder::class,
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

test('importar categorias via planilha enfileira job', function (): void {
    Storage::fake('local');
    Queue::fake();

    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForCategoriesImport('cat-import-ok');
    assignTenantAdminForCategoriesImport($user, $tenant->id);

    $host = tenantImportHost('cat-import-ok');

    $response = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->post(route('tenant.categories.import', ['subdomain' => 'cat-import-ok'], false), [
            'spreadsheet' => UploadedFile::fake()->create(
                'categorias.xlsx',
                30,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ),
        ]);

    $response->assertRedirect(route('tenant.categories.index', ['subdomain' => 'cat-import-ok'], false));
    Queue::assertPushed(ImportCategoriesFromSpreadsheetJob::class);
});

test('importacao sem arquivo falha validacao', function (): void {
    Queue::fake();

    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForCategoriesImport('cat-import-invalid');
    assignTenantAdminForCategoriesImport($user, $tenant->id);

    $host = tenantImportHost('cat-import-invalid');

    $response = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->from(route('tenant.categories.index', ['subdomain' => 'cat-import-invalid'], false))
        ->post(route('tenant.categories.import', ['subdomain' => 'cat-import-invalid'], false), []);

    $response->assertSessionHasErrors(['spreadsheet']);
    Queue::assertNotPushed(ImportCategoriesFromSpreadsheetJob::class);
});

test('exportacao de modelo responde com sucesso', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForCategoriesImport('cat-export-tpl');
    assignTenantAdminForCategoriesImport($user, $tenant->id);

    $host = tenantImportHost('cat-export-tpl');

    $this->withServerVariables(['HTTP_HOST' => $host])
        ->get(route('tenant.categories.export.template', ['subdomain' => 'cat-export-tpl'], false))
        ->assertSuccessful();
});

test('exportacao de dados responde com sucesso', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForCategoriesImport('cat-export-data');
    assignTenantAdminForCategoriesImport($user, $tenant->id);

    $host = tenantImportHost('cat-export-data');

    $this->withServerVariables(['HTTP_HOST' => $host])
        ->get(route('tenant.categories.export.data', ['subdomain' => 'cat-export-data'], false))
        ->assertSuccessful();
});

function tenantImportHost(string $subdomain): string
{
    return $subdomain.'.'.config('app.landlord_domain');
}

/**
 * @return array<string, mixed>
 */
function tenantDatabaseAttributesForCategoriesImport(): array
{
    $defaultConnection = (string) config('database.default');

    return (array) config("database.connections.{$defaultConnection}");
}

function makeTenantForCategoriesImport(string $subdomain): Tenant
{
    $databaseAttributes = tenantDatabaseAttributesForCategoriesImport();

    $tenant = Tenant::query()->create([
        'name' => strtoupper($subdomain),
        'slug' => $subdomain,
        'database' => (string) ($databaseAttributes['database'] ?? 'database.sqlite'),
        'status' => 'active',
    ]);

    $tenant->domains()->create([
        'host' => tenantImportHost($subdomain),
        'type' => 'subdomain',
        'is_primary' => true,
        'is_active' => true,
    ]);

    return $tenant;
}

function assignTenantAdminForCategoriesImport(User $user, string $tenantId): void
{
    $role = Role::query()->where('system_name', 'tenant-admin')->firstOrFail();

    setPermissionsTeamId($tenantId);
    $user->assignRole($role);
}
