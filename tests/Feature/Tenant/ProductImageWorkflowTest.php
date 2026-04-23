<?php

use App\Jobs\ProcessProductImageWithAiJob;
use App\Models\ProductImageAiOperation;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
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

test('tenant can upload product image', function (): void {
    Storage::fake('public');

    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeImageTenant('tenant-image-upload');
    assignImageTenantAdminRole($user, $tenant->id);

    $response = $this
        ->post(route('tenant.products.image.upload', ['subdomain' => 'tenant-image-upload']), [
            'file' => UploadedFile::fake()->image('product.png', 600, 600),
        ]);

    $response
        ->assertOk()
        ->assertJsonStructure(['path', 'public_url']);

    $path = (string) $response->json('path');

    expect($path)->toStartWith("products/uploads/{$tenant->id}/");
    Storage::disk('public')->assertExists($path);
});

test('tenant upload rejects invalid file', function (): void {
    Storage::fake('public');

    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeImageTenant('tenant-image-invalid');
    assignImageTenantAdminRole($user, $tenant->id);

    $response = $this
        ->post(route('tenant.products.image.upload', ['subdomain' => 'tenant-image-invalid']), [
            'file' => UploadedFile::fake()->create('document.pdf', 200, 'application/pdf'),
        ]);

    $response
        ->assertSessionHasErrors(['file']);
});

test('tenant can queue ai processing for uploaded image', function (): void {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeImageTenant('tenant-image-ai');
    assignImageTenantAdminRole($user, $tenant->id);

    $path = "products/uploads/{$tenant->id}/source.png";
    Storage::disk('public')->put($path, UploadedFile::fake()->image('source.png')->getContent());

    $response = $this
        ->post(route('tenant.products.image.ai.process', ['subdomain' => 'tenant-image-ai']), [
            'path' => $path,
        ]);

    $response
        ->assertStatus(202)
        ->assertJsonStructure(['id', 'status']);

    $operationId = (string) $response->json('id');

    $this->assertDatabaseHas('product_image_ai_operations', [
        'id' => $operationId,
        'tenant_id' => $tenant->id,
        'source_path' => $path,
        'status' => 'queued',
    ]);

    Queue::assertPushed(ProcessProductImageWithAiJob::class, function (ProcessProductImageWithAiJob $job) use ($operationId): bool {
        return $job->operationId === $operationId;
    });
});

test('tenant cannot read ai status from another tenant operation', function (): void {
    Storage::fake('public');

    $user = User::factory()->create();
    $this->actingAs($user);

    $tenantA = makeImageTenant('tenant-image-status-a');
    $tenantB = makeImageTenant('tenant-image-status-b');
    assignImageTenantAdminRole($user, $tenantA->id);
    assignImageTenantAdminRole($user, $tenantB->id);

    $operation = ProductImageAiOperation::query()->create([
        'tenant_id' => $tenantA->id,
        'user_id' => $user->id,
        'source_path' => "products/uploads/{$tenantA->id}/source.png",
        'status' => 'queued',
    ]);

    $response = $this
        ->get(route('tenant.products.image.ai.status', [
            'subdomain' => 'tenant-image-status-b',
            'operation' => $operation->id,
        ]));

    $response->assertNotFound();
});

/**
 * @return array<string, mixed>
 */
function imageTenantDatabaseAttributes(): array
{
    $defaultConnection = (string) config('database.default');

    return (array) config("database.connections.{$defaultConnection}");
}

function makeImageTenant(string $subdomain): Tenant
{
    $databaseAttributes = imageTenantDatabaseAttributes();

    $tenant = Tenant::query()->create([
        'name' => strtoupper($subdomain),
        'slug' => $subdomain,
        'database' => sprintf('%s_%s', (string) ($databaseAttributes['database'] ?? 'database'), $subdomain),
        'status' => 'active',
    ]);

    $tenant->domains()->create([
        'host' => $subdomain.'.'.config('app.landlord_domain'),
        'type' => 'subdomain',
        'is_primary' => true,
        'is_active' => true,
    ]);

    return $tenant;
}

function assignImageTenantAdminRole(User $user, string $tenantId): void
{
    $role = Role::query()->where('system_name', 'tenant-admin')->firstOrFail();

    setPermissionsTeamId($tenantId);
    $user->assignRole($role);
}
