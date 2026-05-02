<?php

use App\Models\Tenant;
use App\Models\TenantIntegration;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    config([
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
    ]);

    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

test('tenant integration encrypted arrays can be stored on landlord connection', function (): void {
    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::factory()->create());

    $integration = TenantIntegration::create([
        'tenant_id' => $tenant->id,
        'integration_type' => 'sysmo',
        'identifier' => 'client-1',
        'http_method' => 'POST',
        'api_url' => 'https://sysmo.example.com',
        'authentication_headers' => [
            'auth_username' => 'planner-user',
            'auth_password' => 'planner-pass',
        ],
        'authentication_body' => [
            'partner_key' => 'partner-123',
        ],
        'config' => [
            'processing' => [
                'sales_initial_days' => 120,
            ],
        ],
        'is_active' => true,
    ]);

    $integration->refresh();

    expect($integration->authentication_headers['auth_username'])->toBe('planner-user')
        ->and($integration->authentication_body['partner_key'])->toBe('partner-123')
        ->and($integration->config['processing']['sales_initial_days'])->toBe(120);
});
