<?php

use App\Jobs\ProcessProductImageWithAiJob;
use App\Models\ProductImageAiOperation;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Multitenancy\Http\Middleware\NeedsTenant;

beforeEach(function (): void {
    Http::fake([
        '*' => Http::response([], 404),
    ]);

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
    $this->withoutMiddleware(NeedsTenant::class);

    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeImageTenant('tenant-image-upload');
    assignImageTenantAdminRole($user, $tenant->id);
    $tenant->makeCurrent();

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tenant-image-upload.'.config('app.landlord_domain')])
        ->post(route('tenant.products.image.upload', ['subdomain' => 'tenant-image-upload'], false), [
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
    $this->withoutMiddleware(NeedsTenant::class);

    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeImageTenant('tenant-image-invalid');
    assignImageTenantAdminRole($user, $tenant->id);
    $tenant->makeCurrent();

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tenant-image-invalid.'.config('app.landlord_domain')])
        ->post(route('tenant.products.image.upload', ['subdomain' => 'tenant-image-invalid'], false), [
            'file' => UploadedFile::fake()->create('document.pdf', 200, 'application/pdf'),
        ]);

    $response
        ->assertSessionHasErrors(['file']);
});

test('tenant can queue ai processing for uploaded image', function (): void {
    Storage::fake('public');
    Queue::fake();
    $this->withoutMiddleware(NeedsTenant::class);

    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeImageTenant('tenant-image-ai');
    assignImageTenantAdminRole($user, $tenant->id);
    $tenant->makeCurrent();

    $path = "products/uploads/{$tenant->id}/source.png";
    Storage::disk('public')->put($path, UploadedFile::fake()->image('source.png')->getContent());

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tenant-image-ai.'.config('app.landlord_domain')])
        ->post(route('tenant.products.image.ai.process', ['subdomain' => 'tenant-image-ai'], false), [
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

test('tenant cannot read ai status from operation of another tenant id', function (): void {
    Storage::fake('public');
    $this->withoutMiddleware(NeedsTenant::class);

    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeImageTenant('tenant-image-status');
    assignImageTenantAdminRole($user, $tenant->id);
    $tenant->makeCurrent();

    $operation = ProductImageAiOperation::query()->create([
        'tenant_id' => (string) Str::ulid(),
        'user_id' => $user->id,
        'source_path' => "products/uploads/{$tenant->id}/source.png",
        'status' => 'queued',
    ]);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tenant-image-status.'.config('app.landlord_domain')])
        ->get(route('tenant.products.image.ai.status', [
            'subdomain' => 'tenant-image-status',
            'operation' => $operation->id,
        ], false));

    $response->assertNotFound();
});

test('tenant can fetch product image from repository when webp exists', function (): void {
    Storage::fake('public');
    Storage::fake('do');
    $this->withoutMiddleware(NeedsTenant::class);

    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeImageTenant('tenant-image-repository-webp');
    assignImageTenantAdminRole($user, $tenant->id);
    $tenant->makeCurrent();

    $ean = '7891234567890';
    $expectedPath = "repositorioimagens/frente/{$ean}.webp";
    Storage::disk('do')->put($expectedPath, 'binary-webp-content');

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tenant-image-repository-webp.'.config('app.landlord_domain')])
        ->post(route('tenant.products.image.repository.fetch', ['subdomain' => 'tenant-image-repository-webp'], false), [
            'ean' => $ean,
        ]);

    $response
        ->assertOk()
        ->assertJson([
            'path' => $expectedPath,
        ]);

    Storage::disk('public')->assertExists($expectedPath);
});

test('tenant can fetch product image from repository using png fallback', function (): void {
    Storage::fake('public');
    Storage::fake('do');
    $this->withoutMiddleware(NeedsTenant::class);

    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeImageTenant('tenant-image-repository-png');
    assignImageTenantAdminRole($user, $tenant->id);
    $tenant->makeCurrent();

    $ean = '7891234567000';
    $pngPath = "repositorioimagens/frente/{$ean}.png";
    $webpPath = "repositorioimagens/frente/{$ean}.webp";
    Storage::disk('do')->put($pngPath, UploadedFile::fake()->image('source.png', 300, 300)->getContent());

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tenant-image-repository-png.'.config('app.landlord_domain')])
        ->post(route('tenant.products.image.repository.fetch', ['subdomain' => 'tenant-image-repository-png'], false), [
            'ean' => $ean,
        ]);

    $response
        ->assertOk()
        ->assertJson([
            'path' => $webpPath,
        ]);

    Storage::disk('public')->assertExists($webpPath);
});

test('tenant repository fetch returns not found for missing ean image', function (): void {
    Storage::fake('public');
    Storage::fake('do');
    $this->withoutMiddleware(NeedsTenant::class);

    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeImageTenant('tenant-image-repository-missing');
    assignImageTenantAdminRole($user, $tenant->id);
    $tenant->makeCurrent();

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tenant-image-repository-missing.'.config('app.landlord_domain')])
        ->post(route('tenant.products.image.repository.fetch', ['subdomain' => 'tenant-image-repository-missing'], false), [
            'ean' => '9999999999999',
        ]);

    $response
        ->assertNotFound()
        ->assertJsonStructure(['message']);
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
        'database' => (string) ($databaseAttributes['database'] ?? 'database.sqlite'),
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
