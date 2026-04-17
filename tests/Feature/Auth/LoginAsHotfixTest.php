<?php

use App\Http\Controllers\DashboardController;
use App\Models\User;
use App\Services\Auth\LoginAsTokenBroker;
use Callcocam\LaravelRaptor\Http\Controllers\LoginAsController as PackageLoginAsController;
use Callcocam\LaravelRaptorFlow\Services\Reports\FlowReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

function loginAsHotfixCreateTenant(): string
{
    $tenantId = (string) Str::ulid();

    DB::connection('landlord')->table('tenants')->insert([
        'id' => $tenantId,
        'name' => 'Tenant Hotfix',
        'slug' => 'tenant-hotfix-'.Str::lower(Str::random(6)),
        'subdomain' => null,
        'domain' => null,
        'database' => null,
        'prefix' => null,
        'email' => null,
        'phone' => null,
        'document' => null,
        'logo' => null,
        'settings' => null,
        'status' => 'published',
        'is_primary' => false,
        'description' => null,
        'created_at' => now(),
        'updated_at' => now(),
        'deleted_at' => null,
    ]);

    return $tenantId;
}

function loginAsHotfixCreateClient(string $tenantId): string
{
    $clientId = (string) Str::ulid();

    DB::connection('landlord')->table('clients')->insert([
        'id' => $clientId,
        'tenant_id' => $tenantId,
        'database' => null,
        'user_id' => null,
        'name' => 'Cliente Hotfix',
        'slug' => 'cliente-hotfix-'.Str::lower(Str::random(6)),
        'cnpj' => null,
        'phone' => null,
        'email' => null,
        'bcg_calculos' => null,
        'abc_calculos' => null,
        'stock_calculos' => null,
        'description' => null,
        'status' => 'published',
        'client_api_type' => null,
        'created_at' => now(),
        'updated_at' => now(),
        'deleted_at' => null,
    ]);

    DB::connection('landlord')->table('tenant_domains')->insert([
        'id' => (string) Str::ulid(),
        'tenant_id' => $tenantId,
        'domainable_type' => \App\Models\Client::class,
        'domainable_id' => $clientId,
        'domain' => 'client-'.Str::lower(Str::random(6)).'.example.test',
        'is_primary' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $clientId;
}

function loginAsHotfixAttachSuperAdminRole(string $userId): void
{
    $roleId = DB::connection('landlord')->table('roles')->where('slug', 'super-admin')->value('id');

    if (! $roleId) {
        $roleId = (string) Str::ulid();
        DB::connection('landlord')->table('roles')->insert([
            'id' => $roleId,
            'name' => 'Super Admin',
            'slug' => 'super-admin',
            'description' => 'Acesso total',
            'status' => 'published',
            'special' => true,
            'tenant_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);
    }

    DB::connection('landlord')->table('role_user')->insert([
        'role_id' => $roleId,
        'user_id' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function loginAsHotfixInsertToken(string $actorUserId, string $tenantId, string $clientId): string
{
    $plainToken = Str::random(64);

    DB::connection('landlord')->table(config('login_as.table', 'login_as_tokens'))->insert([
        'id' => (string) Str::ulid(),
        'token_hash' => hash('sha256', $plainToken),
        'actor_user_id' => $actorUserId,
        'tenant_id' => $tenantId,
        'client_id' => $clientId,
        'expires_at' => now()->addSeconds(90),
        'used_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $plainToken;
}

function loginAsHotfixFakeWorkflowReport(): array
{
    return [
        'summary' => [],
        'filters' => ['values' => []],
        'charts' => [],
        'tables' => [],
    ];
}

it('issues and consumes a one-time login-as token', function () {
    $tenantId = loginAsHotfixCreateTenant();
    $clientId = loginAsHotfixCreateClient($tenantId);

    $user = User::factory()->create([
        'tenant_id' => $tenantId,
    ]);
    loginAsHotfixAttachSuperAdminRole($user->id);

    $broker = app(LoginAsTokenBroker::class);

    $token = $broker->issue($user, $tenantId, $clientId);

    expect($token)->not->toBeNull()
        ->and($token)->not->toBe($user->id);

    $consumed = $broker->consume((string) $token, $tenantId, $clientId);

    expect($consumed)->not->toBeNull()
        ->and($consumed?->actorUserId)->toBe($user->id)
        ->and($consumed?->tenantId)->toBe($tenantId)
        ->and($consumed?->clientId)->toBe($clientId);

    expect($broker->consume((string) $token, $tenantId, $clientId))->toBeNull();
});

it('rejects mismatched and expired login-as token usage', function () {
    $tenantId = loginAsHotfixCreateTenant();
    $clientId = loginAsHotfixCreateClient($tenantId);

    $user = User::factory()->create([
        'tenant_id' => $tenantId,
    ]);
    loginAsHotfixAttachSuperAdminRole($user->id);

    $broker = app(LoginAsTokenBroker::class);
    $token = (string) $broker->issue($user, $tenantId, $clientId);

    expect($broker->consume($token, $tenantId, (string) Str::ulid()))->toBeNull();

    DB::connection('landlord')->table(config('login_as.table', 'login_as_tokens'))
        ->where('token_hash', hash('sha256', $token))
        ->update([
            'expires_at' => now()->subSecond(),
            'updated_at' => now(),
        ]);

    expect($broker->consume($token, $tenantId, $clientId))->toBeNull();
});

it('authenticates through the secure login-as controller when token is valid', function () {
    Route::middleware('web')->get('/__test/login-as', [PackageLoginAsController::class, 'loginAs'])->name('test.login-as');

    $tenantId = loginAsHotfixCreateTenant();
    $clientId = loginAsHotfixCreateClient($tenantId);

    $user = User::factory()->create([
        'tenant_id' => $tenantId,
    ]);
    loginAsHotfixAttachSuperAdminRole($user->id);

    $token = app(LoginAsTokenBroker::class)->issue($user, $tenantId, $clientId);

    config([
        'app.current_tenant_id' => $tenantId,
        'app.current_client_id' => $clientId,
    ]);

    $response = $this->get(route('test.login-as', ['token' => $token]));

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

it('denies login-as for a user without super-admin role', function () {
    Route::middleware('web')->get('/__test/login-as', [PackageLoginAsController::class, 'loginAs'])->name('test.login-as');

    $tenantId = loginAsHotfixCreateTenant();
    $clientId = loginAsHotfixCreateClient($tenantId);

    $user = User::factory()->create([
        'tenant_id' => $tenantId,
    ]);

    $token = loginAsHotfixInsertToken($user->id, $tenantId, $clientId);

    config([
        'app.current_tenant_id' => $tenantId,
        'app.current_client_id' => $clientId,
    ]);

    $response = $this->get(route('test.login-as', ['token' => $token]));

    $response->assertForbidden();
    $this->assertGuest();
});

it('generates opaque login-as links in dashboard for super-admin users', function () {
    $tenantId = loginAsHotfixCreateTenant();
    $clientId = loginAsHotfixCreateClient($tenantId);

    $user = User::factory()->create([
        'tenant_id' => $tenantId,
    ]);
    loginAsHotfixAttachSuperAdminRole($user->id);

    $flowReportService = \Mockery::mock(FlowReportService::class);
    $flowReportService->shouldReceive('withPreset')->andReturnSelf();
    $flowReportService->shouldReceive('build')->andReturn(loginAsHotfixFakeWorkflowReport());
    app()->instance(FlowReportService::class, $flowReportService);

    config([
        'app.current_tenant_id' => $tenantId,
        'app.current_client_id' => null,
    ]);

    $request = Request::create('/dashboard', 'GET');
    $request->setUserResolver(fn () => $user);
    $request->headers->set('X-Inertia', 'true');
    app()->instance('request', $request);

    $inertia = app(DashboardController::class)->index($request)->toResponse($request);
    $payload = $inertia->getData(true);

    $url = data_get($payload, 'props.clientsWithDomains.0.domain.url');

    expect($url)->toContain('/login-as?token=')
        ->and($url)->not->toContain($user->id);

    $token = null;
    parse_str((string) parse_url((string) $url, PHP_URL_QUERY), $query);
    $token = data_get($query, 'token');

    expect($token)->toBeString()->not->toBeEmpty();
    expect(DB::connection('landlord')->table(config('login_as.table', 'login_as_tokens'))
        ->where('token_hash', hash('sha256', (string) $token))
        ->exists())->toBeTrue();

    expect(data_get($payload, 'props.clientsWithDomains.0.id'))->toBe($clientId);
});

it('hides client login-as links in dashboard for non super-admin users', function () {
    $tenantId = loginAsHotfixCreateTenant();
    loginAsHotfixCreateClient($tenantId);

    $user = User::factory()->create([
        'tenant_id' => $tenantId,
    ]);

    $flowReportService = \Mockery::mock(FlowReportService::class);
    $flowReportService->shouldReceive('withPreset')->andReturnSelf();
    $flowReportService->shouldReceive('build')->andReturn(loginAsHotfixFakeWorkflowReport());
    app()->instance(FlowReportService::class, $flowReportService);

    config([
        'app.current_tenant_id' => $tenantId,
        'app.current_client_id' => null,
    ]);

    $request = Request::create('/dashboard', 'GET');
    $request->setUserResolver(fn () => $user);
    $request->headers->set('X-Inertia', 'true');
    app()->instance('request', $request);

    $inertia = app(DashboardController::class)->index($request)->toResponse($request);
    $payload = $inertia->getData(true);

    expect(data_get($payload, 'props.clientsWithDomains'))->toBeNull();
});
