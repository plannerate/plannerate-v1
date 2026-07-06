<?php

use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantImpersonationToken;
use App\Models\User;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Multitenancy\Models\Tenant as CurrentTenantModel;

beforeEach(function (): void {
    $tenantPath = database_path('testing_tenant_impersonation.sqlite');

    if (file_exists($tenantPath)) {
        unlink($tenantPath);
    }

    touch($tenantPath);

    config([
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        'database.connections.tenant' => [
            'driver' => 'sqlite',
            'database' => $tenantPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ],
    ]);

    DB::purge('tenant');

    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => 'database/migrations',
        '--realpath' => false,
        '--force' => true,
        '--no-interaction' => true,
    ]);

    Artisan::call('db:seed', [
        '--class' => LandlordRbacSeeder::class,
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

afterEach(function (): void {
    CurrentTenantModel::forgetCurrent();
});

test('super-admin can issue an impersonation code', function () {
    $tenant = impersonationTenant();
    $targetUser = impersonationTenantUser($tenant, 'Cliente Alvo');
    $issuer = landlordUserWithRole('super-admin');

    $response = $this->actingAs($issuer)->post(route('landlord.tenants.access.users.impersonate', [
        'tenant' => $tenant,
        'userId' => $targetUser->id,
    ]));

    $response->assertStatus(302);
    expect($response->headers->get('Location'))->toContain('/impersonation/consume/');

    $this->assertDatabaseHas('tenant_impersonation_tokens', [
        'tenant_id' => $tenant->id,
        'target_user_id' => $targetUser->id,
        'status' => 'pending',
    ], 'landlord');
});

test('landlord-admin can issue an impersonation code', function () {
    $tenant = impersonationTenant();
    $targetUser = impersonationTenantUser($tenant);
    $issuer = landlordUserWithRole('landlord-admin');

    expect($issuer->roles()->where('system_name', 'landlord-admin')->exists())->toBeTrue();

    $response = $this->actingAs($issuer)->post(route('landlord.tenants.access.users.impersonate', [
        'tenant' => $tenant,
        'userId' => $targetUser->id,
    ]));

    $response->assertStatus(302);
});

test('user without super-admin or landlord-admin role cannot issue impersonation', function () {
    $tenant = impersonationTenant();
    $targetUser = impersonationTenantUser($tenant);
    $plainUser = User::factory()->create();

    $response = $this->actingAs($plainUser)->post(route('landlord.tenants.access.users.impersonate', [
        'tenant' => $tenant,
        'userId' => $targetUser->id,
    ]));

    $response->assertForbidden();

    $this->assertDatabaseMissing('tenant_impersonation_tokens', [
        'tenant_id' => $tenant->id,
        'target_user_id' => $targetUser->id,
    ], 'landlord');
});

test('full issue, consume and leave round trip logs in as the target and returns to landlord', function () {
    $tenant = impersonationTenant();
    $targetUser = impersonationTenantUser($tenant, 'Cliente Alvo');
    $issuer = landlordUserWithRole('super-admin');

    $issueResponse = $this->actingAs($issuer)->post(route('landlord.tenants.access.users.impersonate', [
        'tenant' => $tenant,
        'userId' => $targetUser->id,
    ]));

    $consumeUrl = $issueResponse->headers->get('Location');
    expect($consumeUrl)->not()->toBeNull();

    // A resolução automática de tenant por host do pacote Spatie roda uma única vez no
    // boot da aplicação (packageBooted), não a cada request simulada do Pest — por isso
    // o teste precisa reproduzir manualmente o que o NeedsTenant assume já resolvido.
    $tenant->makeCurrent();
    $consumeResponse = $this->get($consumeUrl);
    $consumeResponse->assertRedirect(tenantUrl($tenant, route('tenant.dashboard', [], false)));
    $this->assertAuthenticatedAs($targetUser);

    $this->assertDatabaseHas('tenant_impersonation_tokens', [
        'tenant_id' => $tenant->id,
        'target_user_id' => $targetUser->id,
        'status' => 'active',
    ], 'landlord');

    $tenant->makeCurrent();
    $leaveResponse = $this->post(tenantUrl($tenant, route('tenant.impersonation.leave', [], false)));
    $leaveResponse->assertStatus(302);
    expect($leaveResponse->headers->get('Location'))->toContain('/tenants/'.$tenant->id.'/access');

    $this->assertDatabaseHas('tenant_impersonation_tokens', [
        'tenant_id' => $tenant->id,
        'target_user_id' => $targetUser->id,
        'status' => 'ended',
        'ended_reason' => 'left',
    ], 'landlord');
});

test('consuming an already consumed code fails', function () {
    $tenant = impersonationTenant();
    $targetUser = impersonationTenantUser($tenant);
    $issuer = landlordUserWithRole('super-admin');

    $issueResponse = $this->actingAs($issuer)->post(route('landlord.tenants.access.users.impersonate', [
        'tenant' => $tenant,
        'userId' => $targetUser->id,
    ]));
    $consumeUrl = $issueResponse->headers->get('Location');

    $tenant->makeCurrent();
    $this->get($consumeUrl)->assertRedirect(tenantUrl($tenant, route('tenant.dashboard', [], false)));

    $tenant->makeCurrent();
    $secondAttempt = $this->get($consumeUrl);
    $secondAttempt->assertRedirect(route('login'));
    $secondAttempt->assertSessionHasErrors('email');
});

test('consuming an expired code fails', function () {
    $tenant = impersonationTenant();
    $targetUser = impersonationTenantUser($tenant);
    $issuer = landlordUserWithRole('super-admin');

    $issueResponse = $this->actingAs($issuer)->post(route('landlord.tenants.access.users.impersonate', [
        'tenant' => $tenant,
        'userId' => $targetUser->id,
    ]));
    $consumeUrl = $issueResponse->headers->get('Location');

    TenantImpersonationToken::query()->update(['expires_at' => now()->subMinute()]);

    $tenant->makeCurrent();
    $response = $this->get($consumeUrl);
    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');

    $this->assertDatabaseHas('tenant_impersonation_tokens', [
        'tenant_id' => $tenant->id,
        'status' => 'ended',
        'ended_reason' => 'expired_code',
    ], 'landlord');
});

test('sensitive settings actions are blocked while impersonating but read-only pages stay open', function () {
    $tenant = impersonationTenant();
    $targetUser = impersonationTenantUser($tenant, 'Cliente Alvo', 'alvo@tenant.test');
    $issuer = landlordUserWithRole('super-admin');

    $issueResponse = $this->actingAs($issuer)->post(route('landlord.tenants.access.users.impersonate', [
        'tenant' => $tenant,
        'userId' => $targetUser->id,
    ]));
    $tenant->makeCurrent();
    $this->get($issueResponse->headers->get('Location'));

    $this->patch(tenantUrl($tenant, route('profile.update', [], false)), [
        'name' => 'Novo Nome',
        'email' => 'alvo@tenant.test',
    ])->assertForbidden();

    $this->put(tenantUrl($tenant, route('user-password.update', [], false)), [
        'current_password' => 'password123',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertForbidden();

    $this->delete(tenantUrl($tenant, route('other-browser-sessions.destroy', [], false)), [
        'password' => 'password123',
    ])->assertForbidden();

    $this->delete(tenantUrl($tenant, route('profile.destroy', [], false)), [
        'password' => 'password123',
    ])->assertForbidden();

    // profile.edit (GET) não exige password.confirm (diferente de security.edit, que o
    // SecurityController já exige nativamente do Fortify) — é o caso simples de "somente
    // leitura continua acessível" que este teste quer provar.
    $this->get(tenantUrl($tenant, route('profile.edit', [], false)))->assertOk();
});

test('deactivating the impersonated user during an active session forces logout', function () {
    $tenant = impersonationTenant();
    $targetUser = impersonationTenantUser($tenant, 'Cliente Alvo');
    $issuer = landlordUserWithRole('super-admin');

    $issueResponse = $this->actingAs($issuer)->post(route('landlord.tenants.access.users.impersonate', [
        'tenant' => $tenant,
        'userId' => $targetUser->id,
    ]));
    $tenant->makeCurrent();
    $this->get($issueResponse->headers->get('Location'));

    $tenant->makeCurrent();
    $targetUser->update(['is_active' => false]);
    CurrentTenantModel::forgetCurrent();

    // O guard de sessão cacheia o usuário resolvido na mesma instância do container, que
    // persiste entre chamadas simuladas dentro de um único teste (diferente de produção,
    // onde cada request é um boot novo) — força uma resolução fresca a partir da sessão.
    Auth::forgetGuards();

    $tenant->makeCurrent();
    $response = $this->get(tenantUrl($tenant, route('tenant.dashboard', [], false)));
    $response->assertRedirect(route('login'));
    $this->assertGuest();

    $this->assertDatabaseHas('tenant_impersonation_tokens', [
        'tenant_id' => $tenant->id,
        'target_user_id' => $targetUser->id,
        'status' => 'ended',
        'ended_reason' => 'target_unavailable',
    ], 'landlord');
});

function impersonationTenant(): Tenant
{
    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Impersonation',
        'slug' => 'tenant-impersonation-'.fake()->unique()->numberBetween(1000, 999999),
        'database' => (string) config('database.connections.tenant.database'),
        'status' => 'active',
        'plan_id' => null,
    ]));

    $tenant->domains()->create([
        'host' => 'impersonation-'.fake()->unique()->numberBetween(1000, 999999).'.'.config('app.landlord_domain'),
        'type' => 'subdomain',
        'is_primary' => true,
        'is_active' => true,
    ]);

    return $tenant->fresh('primaryDomain');
}

function impersonationTenantUser(Tenant $tenant, string $name = 'Usuario Tenant', ?string $email = null): User
{
    $tenant->makeCurrent();

    $tenantUser = User::query()->create([
        'name' => $name,
        'email' => $email ?? fake()->unique()->safeEmail(),
        'email_verified_at' => now(),
        'password' => 'password123',
        'is_active' => true,
    ]);

    CurrentTenantModel::forgetCurrent();

    return $tenantUser;
}

function landlordUserWithRole(string $roleSystemName): User
{
    $user = User::factory()->create();
    $role = Role::query()->where('system_name', $roleSystemName)->whereNull('tenant_id')->firstOrFail();

    setPermissionsTeamId(null);
    $user->syncRoles([$role]);

    // syncRoles() não invalida sozinho a relação "roles" já resolvida na instância —
    // mesmo padrão defensivo usado por SetPermissionTeamContext ao trocar o team id.
    $user->unsetRelation('roles');
    $user->unsetRelation('permissions');

    return $user;
}

function tenantUrl(Tenant $tenant, string $path): string
{
    $path = ltrim($path, '/');

    return $path === ''
        ? sprintf('http://%s', $tenant->primaryDomain->host)
        : sprintf('http://%s/%s', $tenant->primaryDomain->host, $path);
}
