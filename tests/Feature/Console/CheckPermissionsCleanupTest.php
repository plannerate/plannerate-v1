<?php

use App\Models\User;
use Callcocam\LaravelRaptor\Enums\PermissionStatus;
use Callcocam\LaravelRaptor\Enums\RoleStatus;
use Callcocam\LaravelRaptor\Services\PermissionCatalogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function insertCleanupPermission(string $slug, string $name): string
{
    $permissionModel = config('raptor.shinobi.models.permission');
    $permissionTable = app($permissionModel)->getTable();
    $id = (string) Str::ulid();

    DB::connection('landlord')->table($permissionTable)->insert([
        'id' => $id,
        'name' => $name,
        'slug' => $slug,
        'description' => $name,
        'context' => 'tenant',
        'status' => PermissionStatus::Published->value,
        'tenant_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
        'deleted_at' => null,
    ]);

    return $id;
}

it('migrates links and soft-deletes redundant permissions with cleanup flag', function () {
    $permissionsTable = app(config('raptor.shinobi.models.permission'))->getTable();
    $rolesTable = config('raptor.shinobi.tables.roles', config('raptor.tables.roles', 'roles'));
    $permissionRoleTable = config('raptor.shinobi.tables.permission_role', config('raptor.tables.permission_role', 'permission_role'));
    $permissionUserTable = config('raptor.shinobi.tables.permission_user', config('raptor.tables.permission_user', 'permission_user'));

    $storePermissionId = insertCleanupPermission('tenant.stores.store', 'Stores Store');
    $updatePermissionId = insertCleanupPermission('tenant.products.update', 'Products Update');
    $destroyPermissionId = insertCleanupPermission('tenant.clients.destroy', 'Clients Destroy');
    $executePermissionId = insertCleanupPermission('tenant.categories.execute', 'Categories Execute');

    $roleId = (string) Str::ulid();
    DB::connection('landlord')->table($rolesTable)->insert([
        'id' => $roleId,
        'name' => 'Cleanup Role',
        'slug' => 'cleanup-role-'.Str::lower(Str::random(6)),
        'description' => 'Role for cleanup test',
        'status' => RoleStatus::Published->value,
        'special' => false,
        'tenant_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
        'deleted_at' => null,
    ]);

    $user = User::factory()->create([
        'slug' => 'cleanup-user-'.Str::lower(Str::random(8)),
    ]);

    DB::connection('landlord')->table($permissionRoleTable)->insert([
        ['permission_id' => $storePermissionId, 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now()],
        ['permission_id' => $updatePermissionId, 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now()],
        ['permission_id' => $executePermissionId, 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now()],
    ]);

    DB::connection('landlord')->table($permissionUserTable)->insert([
        ['permission_id' => $storePermissionId, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()],
        ['permission_id' => $destroyPermissionId, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $this->artisan('permissions:check', ['--cleanup-redundant' => true])
        ->assertExitCode(0);

    $storeCanonical = DB::connection('landlord')->table($permissionsTable)
        ->where('slug', 'tenant.stores.create')
        ->first();
    $updateCanonical = DB::connection('landlord')->table($permissionsTable)
        ->where('slug', 'tenant.products.edit')
        ->first();
    $destroyCanonical = DB::connection('landlord')->table($permissionsTable)
        ->where('slug', 'tenant.clients.delete')
        ->first();
    $executeCanonical = DB::connection('landlord')->table($permissionsTable)
        ->where('slug', 'tenant.categories.create')
        ->first();

    expect($storeCanonical)->not->toBeNull();
    expect($updateCanonical)->not->toBeNull();
    expect($destroyCanonical)->not->toBeNull();
    expect($executeCanonical)->not->toBeNull();
    expect($storeCanonical->deleted_at)->toBeNull();
    expect($updateCanonical->deleted_at)->toBeNull();
    expect($destroyCanonical->deleted_at)->toBeNull();
    expect($executeCanonical->deleted_at)->toBeNull();

    expect(DB::connection('landlord')->table($permissionRoleTable)
        ->where('permission_id', $storeCanonical->id)
        ->where('role_id', $roleId)
        ->exists())->toBeTrue();
    expect(DB::connection('landlord')->table($permissionRoleTable)
        ->where('permission_id', $updateCanonical->id)
        ->where('role_id', $roleId)
        ->exists())->toBeTrue();
    expect(DB::connection('landlord')->table($permissionRoleTable)
        ->where('permission_id', $executeCanonical->id)
        ->where('role_id', $roleId)
        ->exists())->toBeTrue();

    expect(DB::connection('landlord')->table($permissionUserTable)
        ->where('permission_id', $storeCanonical->id)
        ->where('user_id', $user->id)
        ->exists())->toBeTrue();
    expect(DB::connection('landlord')->table($permissionUserTable)
        ->where('permission_id', $destroyCanonical->id)
        ->where('user_id', $user->id)
        ->exists())->toBeTrue();

    expect(DB::connection('landlord')->table($permissionRoleTable)->where('permission_id', $storePermissionId)->exists())->toBeFalse();
    expect(DB::connection('landlord')->table($permissionRoleTable)->where('permission_id', $updatePermissionId)->exists())->toBeFalse();
    expect(DB::connection('landlord')->table($permissionRoleTable)->where('permission_id', $executePermissionId)->exists())->toBeFalse();
    expect(DB::connection('landlord')->table($permissionUserTable)->where('permission_id', $storePermissionId)->exists())->toBeFalse();
    expect(DB::connection('landlord')->table($permissionUserTable)->where('permission_id', $destroyPermissionId)->exists())->toBeFalse();

    $storeRedundant = DB::connection('landlord')->table($permissionsTable)->where('id', $storePermissionId)->first();
    $updateRedundant = DB::connection('landlord')->table($permissionsTable)->where('id', $updatePermissionId)->first();
    $destroyRedundant = DB::connection('landlord')->table($permissionsTable)->where('id', $destroyPermissionId)->first();
    $executeRedundant = DB::connection('landlord')->table($permissionsTable)->where('id', $executePermissionId)->first();

    expect($storeRedundant->deleted_at)->not->toBeNull();
    expect($updateRedundant->deleted_at)->not->toBeNull();
    expect($destroyRedundant->deleted_at)->not->toBeNull();
    expect($executeRedundant->deleted_at)->not->toBeNull();
});

it('soft-deletes ignored permissions with cleanup-ignored flag', function () {
    $permissionsTable = app(config('raptor.shinobi.models.permission'))->getTable();

    $ignoredApiResource = insertCleanupPermission('tenant.api.sections.index', 'Api Sections Index');
    $ignoredCompositeResource = insertCleanupPermission('tenant.api-sections.edit', 'Api Sections Edit');
    $keptPermission = insertCleanupPermission('tenant.products.create', 'Products Create');

    $this->artisan('permissions:check', ['--cleanup-ignored' => true])
        ->assertExitCode(0);

    $ignoredOne = DB::connection('landlord')->table($permissionsTable)->where('id', $ignoredApiResource)->first();
    $ignoredTwo = DB::connection('landlord')->table($permissionsTable)->where('id', $ignoredCompositeResource)->first();
    $kept = DB::connection('landlord')->table($permissionsTable)->where('id', $keptPermission)->first();

    expect($ignoredOne)->not->toBeNull();
    expect($ignoredTwo)->not->toBeNull();
    expect($kept)->not->toBeNull();

    expect($ignoredOne->deleted_at)->not->toBeNull();
    expect($ignoredTwo->deleted_at)->not->toBeNull();
    expect($kept->deleted_at)->toBeNull();
});

it('hard-resets permissions and recreates expected canonical catalog', function () {
    $permissionsTable = app(config('raptor.shinobi.models.permission'))->getTable();
    $rolesTable = config('raptor.shinobi.tables.roles', config('raptor.tables.roles', 'roles'));
    $permissionRoleTable = config('raptor.shinobi.tables.permission_role', config('raptor.tables.permission_role', 'permission_role'));
    $permissionUserTable = config('raptor.shinobi.tables.permission_user', config('raptor.tables.permission_user', 'permission_user'));

    $legacyPermissionId = insertCleanupPermission('tenant.legacy.index', 'Legacy Index');

    $roleId = (string) Str::ulid();
    DB::connection('landlord')->table($rolesTable)->insert([
        'id' => $roleId,
        'name' => 'Reset Role',
        'slug' => 'reset-role-'.Str::lower(Str::random(6)),
        'description' => 'Role for reset test',
        'status' => RoleStatus::Published->value,
        'special' => false,
        'tenant_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
        'deleted_at' => null,
    ]);

    $user = User::factory()->create([
        'slug' => 'reset-user-'.Str::lower(Str::random(8)),
    ]);

    DB::connection('landlord')->table($permissionRoleTable)->insert([
        'permission_id' => $legacyPermissionId,
        'role_id' => $roleId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::connection('landlord')->table($permissionUserTable)->insert([
        'permission_id' => $legacyPermissionId,
        'user_id' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $expectedTenant = app(PermissionCatalogService::class)->expectedPermissions('tenant', true);
    expect($expectedTenant->isNotEmpty())->toBeTrue();

    $this->artisan('permissions:check', [
        '--reset' => true,
        '--context' => 'tenant',
        '--force' => true,
    ])->assertExitCode(0);

    expect(DB::connection('landlord')->table($permissionsTable)->where('slug', 'tenant.legacy.index')->exists())->toBeFalse();
    expect(DB::connection('landlord')->table($permissionRoleTable)->where('permission_id', $legacyPermissionId)->exists())->toBeFalse();
    expect(DB::connection('landlord')->table($permissionUserTable)->where('permission_id', $legacyPermissionId)->exists())->toBeFalse();

    $sampleSlugs = $expectedTenant->pluck('slug')->take(3)->values();
    foreach ($sampleSlugs as $slug) {
        expect(DB::connection('landlord')->table($permissionsTable)
            ->where('slug', $slug)
            ->whereNull('deleted_at')
            ->exists())->toBeTrue();
    }
});
