<?php

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    $this->actingAs(User::factory()->create());

    config([
        'cloudflare.api_token' => 'test-token-abc',
        'cloudflare.zone_id' => 'zone-abc123',
        'cloudflare.cname_target' => 'app.example.com',
    ]);
});

function tenantWithHost(string $host = 'client.example.com'): Tenant
{
    $tenant = Tenant::factory()->create(['status' => 'active']);

    TenantDomain::query()->create([
        'tenant_id' => $tenant->id,
        'host' => $host,
        'type' => 'subdomain',
        'is_primary' => true,
        'is_active' => true,
    ]);

    return $tenant;
}

test('store creates CNAME record and redirects back', function (): void {
    $tenant = tenantWithHost('client.example.com');

    Http::fake([
        'api.cloudflare.com/*' => Http::response([
            'success' => true,
            'result' => [
                'id' => 'rec123',
                'type' => 'CNAME',
                'name' => 'client.example.com',
                'content' => 'app.example.com',
            ],
        ], 200),
    ]);

    $response = $this->post(route('landlord.tenants.cloudflare.store', $tenant));

    $response->assertRedirect(route('landlord.tenants.edit', $tenant));

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'zones/zone-abc123/dns_records')
        && $request->data()['type'] === 'CNAME'
        && $request->data()['name'] === 'client.example.com'
        && $request->data()['content'] === 'app.example.com'
    );
});

test('store redirects with error when api_token is empty', function (): void {
    config(['cloudflare.api_token' => '']);
    $tenant = tenantWithHost();

    $response = $this->post(route('landlord.tenants.cloudflare.store', $tenant));

    $response->assertRedirect(route('landlord.tenants.edit', $tenant));
    Http::assertNothingSent();
});

test('store redirects with error when zone_id is empty', function (): void {
    config(['cloudflare.zone_id' => '']);
    $tenant = tenantWithHost();

    $response = $this->post(route('landlord.tenants.cloudflare.store', $tenant));

    $response->assertRedirect(route('landlord.tenants.edit', $tenant));
    Http::assertNothingSent();
});

test('store redirects with error when tenant has no host', function (): void {
    $tenant = Tenant::factory()->create(['status' => 'active']);

    $response = $this->post(route('landlord.tenants.cloudflare.store', $tenant));

    $response->assertRedirect(route('landlord.tenants.edit', $tenant));
    Http::assertNothingSent();
});

test('store redirects with error when Cloudflare API returns failure', function (): void {
    $tenant = tenantWithHost();

    Http::fake([
        'api.cloudflare.com/*' => Http::response([
            'success' => false,
            'errors' => [['message' => 'Invalid record name']],
        ], 400),
    ]);

    $response = $this->post(route('landlord.tenants.cloudflare.store', $tenant));

    $response->assertRedirect(route('landlord.tenants.edit', $tenant));
});

test('destroy deletes DNS record when found', function (): void {
    $tenant = tenantWithHost('client.example.com');

    Http::fake([
        'api.cloudflare.com/*/dns_records*' => Http::sequence()
            ->push([
                'success' => true,
                'result' => [[
                    'id' => 'rec123',
                    'type' => 'CNAME',
                    'name' => 'client.example.com',
                    'content' => 'app.example.com',
                ]],
            ], 200)
            ->push([
                'success' => true,
                'result' => ['id' => 'rec123'],
            ], 200),
    ]);

    $response = $this->delete(route('landlord.tenants.cloudflare.destroy', $tenant));

    $response->assertRedirect(route('landlord.tenants.edit', $tenant));
});

test('destroy redirects with warning when no record found', function (): void {
    $tenant = tenantWithHost();

    Http::fake([
        'api.cloudflare.com/*' => Http::response([
            'success' => true,
            'result' => [],
        ], 200),
    ]);

    $response = $this->delete(route('landlord.tenants.cloudflare.destroy', $tenant));

    $response->assertRedirect(route('landlord.tenants.edit', $tenant));
    Http::assertSentCount(1);
});

test('destroy redirects with error when tenant has no host', function (): void {
    $tenant = Tenant::factory()->create(['status' => 'active']);

    $response = $this->delete(route('landlord.tenants.cloudflare.destroy', $tenant));

    $response->assertRedirect(route('landlord.tenants.edit', $tenant));
    Http::assertNothingSent();
});
