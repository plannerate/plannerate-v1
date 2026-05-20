<?php

use App\Models\EanReference;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    config(['permission.rbac_enabled' => true]);

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

test('tenant can sync dimensions from ean reference for one product', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenant('tenant-dim-sync-single');
    assignTenantAdminRole($user, $tenant->id);

    $host = 'tenant-dim-sync-single.'.config('app.landlord_domain');

    $product = Product::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Produto sem dimensao',
        'slug' => 'produto-sem-dimensao',
        'status' => 'published',
        'ean' => '789.123.456.789-0',
    ]);

    EanReference::query()->create([
        'ean' => '7891234567890',
        'width' => 10.50,
        'height' => 20.25,
        'depth' => 30.75,
        'weight' => 150.00,
        'unit' => 'cm',
        'has_dimensions' => true,
        'dimension_status' => 'published',
    ]);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->post(route('tenant.dimensions.sync-from-reference', [
            'subdomain' => 'tenant-dim-sync-single',
            'product' => $product->id,
        ], false));

    $response->assertRedirect();

    $product->refresh();

    expect((float) $product->width)->toBe(10.5)
        ->and((float) $product->height)->toBe(20.25)
        ->and((float) $product->depth)->toBe(30.75)
        ->and((float) $product->weight)->toBe(150.0)
        ->and($product->unit)->toBe('cm');
});

test('bulk sync updates only products without configured dimensions', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenant('tenant-dim-sync-page');
    assignTenantAdminRole($user, $tenant->id);

    $host = 'tenant-dim-sync-page.'.config('app.landlord_domain');

    $missingDimensions = Product::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Sem dimensao',
        'slug' => 'sem-dimensao',
        'status' => 'published',
        'ean' => '1111111111111',
    ]);

    $alreadyConfigured = Product::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Ja configurado',
        'slug' => 'ja-configurado',
        'status' => 'published',
        'ean' => '2222222222222',
        'width' => 9.99,
    ]);

    EanReference::query()->create([
        'ean' => '1111111111111',
        'width' => 11.00,
        'height' => 12.00,
        'depth' => 13.00,
        'weight' => 14.00,
        'unit' => 'cm',
        'has_dimensions' => true,
        'dimension_status' => 'published',
    ]);

    EanReference::query()->create([
        'ean' => '2222222222222',
        'width' => 21.00,
        'height' => 22.00,
        'depth' => 23.00,
        'weight' => 24.00,
        'unit' => 'cm',
        'has_dimensions' => true,
        'dimension_status' => 'published',
    ]);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->post(route('tenant.dimensions.sync-from-reference-page', [
            'subdomain' => 'tenant-dim-sync-page',
        ], false), [
            'product_ids' => [
                $missingDimensions->id,
                $alreadyConfigured->id,
            ],
        ]);

    $response->assertRedirect();

    $missingDimensions->refresh();
    $alreadyConfigured->refresh();

    expect((float) $missingDimensions->width)->toBe(11.0)
        ->and((float) $missingDimensions->height)->toBe(12.0)
        ->and((float) $missingDimensions->depth)->toBe(13.0)
        ->and((float) $alreadyConfigured->width)->toBe(9.99)
        ->and($alreadyConfigured->height)->toBeNull()
        ->and($alreadyConfigured->depth)->toBeNull();
});
