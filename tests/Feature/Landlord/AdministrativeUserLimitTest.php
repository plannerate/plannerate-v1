<?php

use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\AdministrativeUserLimitService;
use Database\Seeders\LandlordKanbanStageRolesSeeder;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Testes da verificação central de limite de usuários por perfil administrativo.
 * Toda a lógica vive na conexão landlord (roles, model_has_roles, plan_items),
 * então estes testes não dependem do banco do tenant.
 */
beforeEach(function (): void {
    config()->set('permission.rbac_enabled', true);

    Artisan::call('migrate', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    Artisan::call('db:seed', [
        '--class' => LandlordRbacSeeder::class,
        '--force' => true,
        '--no-interaction' => true,
    ]);

    Artisan::call('db:seed', [
        '--class' => LandlordKanbanStageRolesSeeder::class,
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

/**
 * Cria um tenant vinculado ao plano informado.
 */
function makeTenantForLimit(string $slug, string $planId): Tenant
{
    return Tenant::query()->create([
        'name' => strtoupper($slug),
        'slug' => $slug,
        'database' => 'database.sqlite',
        'status' => 'active',
        'plan_id' => $planId,
    ]);
}

/**
 * Atribui um perfil a um usuário no escopo do tenant (pivot model_has_roles).
 */
function assignRoleInTenant(User $user, Role $role, string $tenantId): void
{
    DB::connection('landlord')->table('model_has_roles')->insert([
        'role_id' => $role->id,
        'model_type' => User::class,
        'model_id' => $user->id,
        'tenant_id' => $tenantId,
    ]);
}

function limitService(): AdministrativeUserLimitService
{
    return app(AdministrativeUserLimitService::class);
}

test('roleUserLimit resolves tenant-admin from plan user_limit and kanban roles from plan_items', function (): void {
    $plan = Plan::factory()->create(['user_limit' => 5]);
    $plan->items()->create([
        'key' => 'user_limit:kanban-revisao-de-imagens',
        'label' => 'Limite imagens',
        'value' => '2',
        'type' => 'integer',
        'is_active' => true,
    ]);

    $tenant = makeTenantForLimit('limit-resolve', $plan->id);

    expect($tenant->roleUserLimit('tenant-admin'))->toBe(5);
    expect($tenant->roleUserLimit('kanban-revisao-de-imagens'))->toBe(2);
    // Perfil administrativo sem plan_item configurado = ilimitado (null).
    expect($tenant->roleUserLimit('kanban-revisao-de-dimensoes'))->toBeNull();
});

test('administrativeRoles includes tenant-admin and the flagged kanban roles', function (): void {
    $systemNames = limitService()->administrativeRoles()->pluck('system_name')->all();

    expect($systemNames)->toContain('tenant-admin')
        ->toContain('kanban-aprovacao-da-area-de-gc')
        ->toContain('kanban-revisao-de-dimensoes')
        ->toContain('kanban-revisao-de-imagens')
        ->toContain('kanban-revisao-periodica');

    // Etapas não administrativas não entram.
    expect($systemNames)->not->toContain('kanban-criacao-do-planograma');
});

test('countUsersWithRole counts distinct users with the role in the tenant', function (): void {
    $plan = Plan::factory()->create(['user_limit' => 5]);
    $tenant = makeTenantForLimit('limit-count', $plan->id);
    $role = Role::query()->where('system_name', 'kanban-revisao-de-imagens')->firstOrFail();

    $userA = User::factory()->create();
    $userB = User::factory()->create();
    assignRoleInTenant($userA, $role, $tenant->id);
    assignRoleInTenant($userB, $role, $tenant->id);

    expect(limitService()->countUsersWithRole($tenant, $role))->toBe(2);
});

test('ensureCanAssign blocks when the role plan limit is reached', function (): void {
    $plan = Plan::factory()->create(['user_limit' => 5]);
    $plan->items()->create([
        'key' => 'user_limit:kanban-revisao-de-imagens',
        'label' => 'Limite imagens',
        'value' => '1',
        'type' => 'integer',
        'is_active' => true,
    ]);

    $tenant = makeTenantForLimit('limit-block', $plan->id);
    $role = Role::query()->where('system_name', 'kanban-revisao-de-imagens')->firstOrFail();

    // Preenche a única vaga.
    assignRoleInTenant(User::factory()->create(), $role, $tenant->id);

    limitService()->ensureCanAssign($tenant, [$role->id]);
})->throws(ValidationException::class);

test('ensureCanAssign allows when below the role plan limit', function (): void {
    $plan = Plan::factory()->create(['user_limit' => 5]);
    $plan->items()->create([
        'key' => 'user_limit:kanban-revisao-de-imagens',
        'label' => 'Limite imagens',
        'value' => '2',
        'type' => 'integer',
        'is_active' => true,
    ]);

    $tenant = makeTenantForLimit('limit-allow', $plan->id);
    $role = Role::query()->where('system_name', 'kanban-revisao-de-imagens')->firstOrFail();

    assignRoleInTenant(User::factory()->create(), $role, $tenant->id);

    // 1 de 2 usados: não deve lançar.
    limitService()->ensureCanAssign($tenant, [$role->id]);

    expect(true)->toBeTrue();
});

test('ensureCanAssign ignores a role the user already has', function (): void {
    $plan = Plan::factory()->create(['user_limit' => 5]);
    $plan->items()->create([
        'key' => 'user_limit:kanban-revisao-de-imagens',
        'label' => 'Limite imagens',
        'value' => '1',
        'type' => 'integer',
        'is_active' => true,
    ]);

    $tenant = makeTenantForLimit('limit-current', $plan->id);
    $role = Role::query()->where('system_name', 'kanban-revisao-de-imagens')->firstOrFail();

    assignRoleInTenant(User::factory()->create(), $role, $tenant->id);

    // Limite atingido, mas o usuário já possui o perfil (currentRoleIds) → não bloqueia.
    limitService()->ensureCanAssign($tenant, [$role->id], [$role->id]);

    expect(true)->toBeTrue();
});

test('ensureCanAssign never blocks non-administrative roles', function (): void {
    $plan = Plan::factory()->create(['user_limit' => 1]);
    $tenant = makeTenantForLimit('limit-nonadmin', $plan->id);
    $role = Role::query()->where('system_name', 'kanban-criacao-do-planograma')->firstOrFail();

    // Muitos usuários com o perfil não-administrativo, sem limite.
    assignRoleInTenant(User::factory()->create(), $role, $tenant->id);
    assignRoleInTenant(User::factory()->create(), $role, $tenant->id);

    limitService()->ensureCanAssign($tenant, [$role->id]);

    expect(true)->toBeTrue();
});

test('rolesForSelect only returns roles linked to the tenant plus always-available system roles', function (): void {
    $plan = Plan::factory()->create(['user_limit' => 5]);
    $tenant = makeTenantForLimit('roles-linked', $plan->id);

    $linked = Role::query()->where('system_name', 'kanban-revisao-de-imagens')->firstOrFail();
    $unlinked = Role::query()->where('system_name', 'kanban-revisao-de-dimensoes')->firstOrFail();

    $tenant->roles()->attach($linked->id);

    $names = collect(limitService()->rolesForSelect($tenant))->pluck('name');

    // O perfil vinculado aparece; o não vinculado, não.
    expect($names)->toContain($linked->name)
        ->not->toContain($unlinked->name);

    // tenant-admin está sempre disponível, mesmo sem vínculo no pivot.
    $tenantAdmin = Role::query()->where('system_name', 'tenant-admin')->firstOrFail();
    expect($names)->toContain($tenantAdmin->name);
});

test('availableRoleIds and availableRoleNames match the tenant catalog', function (): void {
    $plan = Plan::factory()->create(['user_limit' => 5]);
    $tenant = makeTenantForLimit('roles-available', $plan->id);

    $linked = Role::query()->where('system_name', 'kanban-revisao-de-imagens')->firstOrFail();
    $unlinked = Role::query()->where('system_name', 'kanban-revisao-de-dimensoes')->firstOrFail();
    $tenant->roles()->attach($linked->id);

    $ids = limitService()->availableRoleIds($tenant);
    $names = limitService()->availableRoleNames($tenant);

    expect($ids)->toContain($linked->id)->not->toContain($unlinked->id);
    expect($names)->toContain($linked->name)->not->toContain($unlinked->name);
});

test('rolesForSelect reports per-role limit state', function (): void {
    $plan = Plan::factory()->create(['user_limit' => 5]);
    $plan->items()->create([
        'key' => 'user_limit:kanban-revisao-de-imagens',
        'label' => 'Limite imagens',
        'value' => '1',
        'type' => 'integer',
        'is_active' => true,
    ]);

    $tenant = makeTenantForLimit('limit-select', $plan->id);
    $role = Role::query()->where('system_name', 'kanban-revisao-de-imagens')->firstOrFail();
    // O perfil precisa estar vinculado ao tenant para constar no catálogo.
    $tenant->roles()->attach($role->id);
    assignRoleInTenant(User::factory()->create(), $role, $tenant->id);

    $rows = collect(limitService()->rolesForSelect($tenant));
    $imagens = $rows->firstWhere('name', $role->name);

    expect($imagens['is_admin'])->toBeTrue();
    expect($imagens['limit'])->toBe(1);
    expect($imagens['count'])->toBe(1);
    expect($imagens['limit_reached'])->toBeTrue();
});
