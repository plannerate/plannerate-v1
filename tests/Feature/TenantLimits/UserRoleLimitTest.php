<?php

use App\Models\User;
use App\Services\TenantLimitService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

const USER_ROLE_LIMIT_TENANT = 'tenant-role-limit-test-001';

function fakeUserRoleLimitTenant(array $settings = []): object
{
    return (object) [
        'id' => USER_ROLE_LIMIT_TENANT,
        'settings' => $settings,
    ];
}

function insertAdminRole(): string
{
    $id = (string) Str::ulid();
    DB::connection('landlord')->table('roles')->insert([
        'id' => $id,
        'name' => 'Administrador Teste',
        'slug' => 'admin-role-limit-'.Str::random(4),
        'special' => true,
        'status' => 'published',
        'tenant_id' => null, // nullable — sem FK para simplificar os testes
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

function insertGeneralRole(): string
{
    $id = (string) Str::ulid();
    DB::connection('landlord')->table('roles')->insert([
        'id' => $id,
        'name' => 'Usuário Geral Teste',
        'slug' => 'user-role-limit-'.Str::random(4),
        'special' => false,
        'status' => 'published',
        'tenant_id' => null, // nullable — sem FK para simplificar os testes
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

function insertUserWithRole(string $roleId): string
{
    $userId = (string) Str::ulid();
    DB::connection('landlord')->table('users')->insert([
        'id' => $userId,
        'name' => fake()->name(),
        'email' => fake()->unique()->safeEmail(),
        'slug' => Str::slug(fake()->name().'-'.Str::random(5)),
        'password' => bcrypt('password'),
        'tenant_id' => USER_ROLE_LIMIT_TENANT,
        'status' => 'published',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::connection('landlord')->table('role_user')->insert([
        'role_id' => $roleId,
        'user_id' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $userId;
}

afterEach(function () {
    $userIds = DB::connection('landlord')->table('users')
        ->where('tenant_id', USER_ROLE_LIMIT_TENANT)
        ->pluck('id');

    if ($userIds->isNotEmpty()) {
        DB::connection('landlord')->table('role_user')->whereIn('user_id', $userIds)->delete();
    }
    DB::connection('landlord')->table('users')->where('tenant_id', USER_ROLE_LIMIT_TENANT)->delete();
    // Roles foram inseridas com tenant_id = null; identifica pelo slug prefix
    DB::connection('landlord')->table('roles')
        ->where('slug', 'like', 'admin-role-limit-%')
        ->orWhere('slug', 'like', 'user-role-limit-%')
        ->delete();
});

it('enforceByRoles blocks admin creation when max_admins is reached', function () {
    app()->instance('current.tenant', fakeUserRoleLimitTenant(['limits' => ['max_admins' => 1]]));

    $adminRoleId = insertAdminRole();
    insertUserWithRole($adminRoleId);

    expect(fn () => app(TenantLimitService::class)->enforceByRoles([$adminRoleId], User::class))
        ->toThrow(ValidationException::class);
});

it('enforceByRoles allows admin creation when below max_admins', function () {
    app()->instance('current.tenant', fakeUserRoleLimitTenant(['limits' => ['max_admins' => 3]]));

    $adminRoleId = insertAdminRole();
    insertUserWithRole($adminRoleId);

    expect(fn () => app(TenantLimitService::class)->enforceByRoles([$adminRoleId], User::class))
        ->not->toThrow(ValidationException::class);
});

it('enforceByRoles blocks general user creation when max_users is reached', function () {
    app()->instance('current.tenant', fakeUserRoleLimitTenant(['limits' => ['max_users' => 2]]));

    $generalRoleId = insertGeneralRole();
    insertUserWithRole($generalRoleId);
    insertUserWithRole($generalRoleId);

    expect(fn () => app(TenantLimitService::class)->enforceByRoles([$generalRoleId], User::class))
        ->toThrow(ValidationException::class);
});

it('enforceByRoles allows general user creation when below max_users', function () {
    app()->instance('current.tenant', fakeUserRoleLimitTenant(['limits' => ['max_users' => 5]]));

    $generalRoleId = insertGeneralRole();
    insertUserWithRole($generalRoleId);

    expect(fn () => app(TenantLimitService::class)->enforceByRoles([$generalRoleId], User::class))
        ->not->toThrow(ValidationException::class);
});

it('enforceByRoles does not block when no limits are configured', function () {
    app()->instance('current.tenant', fakeUserRoleLimitTenant());

    $adminRoleId = insertAdminRole();
    $generalRoleId = insertGeneralRole();

    for ($i = 0; $i < 5; $i++) {
        insertUserWithRole($generalRoleId);
    }

    expect(fn () => app(TenantLimitService::class)->enforceByRoles([$adminRoleId], User::class))
        ->not->toThrow(ValidationException::class);

    expect(fn () => app(TenantLimitService::class)->enforceByRoles([$generalRoleId], User::class))
        ->not->toThrow(ValidationException::class);
});

it('enforceByRoles counts admin and general users independently', function () {
    app()->instance('current.tenant', fakeUserRoleLimitTenant([
        'limits' => ['max_admins' => 1, 'max_users' => 10],
    ]));

    $adminRoleId = insertAdminRole();
    $generalRoleId = insertGeneralRole();

    insertUserWithRole($adminRoleId);
    for ($i = 0; $i < 3; $i++) {
        insertUserWithRole($generalRoleId);
    }

    // 1 admin já cadastrado — novo admin deve falhar
    expect(fn () => app(TenantLimitService::class)->enforceByRoles([$adminRoleId], User::class))
        ->toThrow(ValidationException::class);

    // 3 gerais cadastrados, limite 10 — deve permitir
    expect(fn () => app(TenantLimitService::class)->enforceByRoles([$generalRoleId], User::class))
        ->not->toThrow(ValidationException::class);
});
