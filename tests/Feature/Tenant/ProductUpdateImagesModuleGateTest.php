<?php

use App\Jobs\ProcessEanReferenceImageJob;
use App\Models\Module;
use App\Models\Product;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Modules\ModuleSlug;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
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
});

if (! function_exists('setupImageGateTenant')) {
    function setupImageGateTenant(string $subdomain, User $user, bool $withImageBank): Tenant
    {
        $tenant = Tenant::query()->create([
            'name' => strtoupper($subdomain),
            'slug' => $subdomain,
            'database' => (string) config('database.connections.'.config('database.default').'.database'),
            'status' => 'active',
        ]);

        $tenant->domains()->create([
            'host' => $subdomain.'.'.config('app.landlord_domain'),
            'type' => 'subdomain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        if ($withImageBank) {
            $module = Module::query()->create([
                'name' => 'Banco de Imagens',
                'slug' => ModuleSlug::IMAGE_BANK,
                'is_active' => true,
            ]);

            $tenant->modules()->attach($module->id);
        }

        app()->instance((string) config('multitenancy.current_tenant_container_key', 'currentTenant'), $tenant);

        if (! Schema::connection('tenant')->hasTable('products')) {
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations',
                '--force' => true,
                '--no-interaction' => true,
            ]);
        }

        $role = Role::query()->where('system_name', 'tenant-admin')->firstOrFail();
        setPermissionsTeamId($tenant->id);
        $user->assignRole($role);

        return $tenant;
    }
}

if (! function_exists('postUpdateImages')) {
    /**
     * @param  list<string>  $eans
     */
    function postUpdateImages(object $test, string $subdomain, array $eans)
    {
        return $test
            ->withServerVariables(['HTTP_HOST' => $subdomain.'.'.config('app.landlord_domain')])
            ->post(route('tenant.products.update-images', ['subdomain' => $subdomain], false), [
                'eans' => $eans,
            ]);
    }
}

test('avisa e não despacha download quando o módulo image-bank está inativo', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = setupImageGateTenant('tenant-img-off', $user, withImageBank: false);

    Product::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Produto sem imagem',
        'slug' => 'produto-sem-imagem',
        'status' => 'published',
        'ean' => '7891234567895',
    ]);

    Queue::fake();

    $response = postUpdateImages($this, 'tenant-img-off', ['7891234567895']);

    $response->assertRedirect();

    Queue::assertNotPushed(ProcessEanReferenceImageJob::class);

    expect(session('inertia.flash_data.toast'))->toMatchArray([
        'type' => 'warning',
        'message' => __('app.tenant.products.images.module_inactive'),
    ]);
});

test('despacha o download em segundo plano quando o módulo image-bank está ativo', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = setupImageGateTenant('tenant-img-on', $user, withImageBank: true);

    Product::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Produto sem imagem',
        'slug' => 'produto-sem-imagem',
        'status' => 'published',
        'ean' => '7891234567895',
    ]);

    Queue::fake();

    $response = postUpdateImages($this, 'tenant-img-on', ['7891234567895']);

    $response->assertRedirect();

    Queue::assertPushed(
        ProcessEanReferenceImageJob::class,
        fn (ProcessEanReferenceImageJob $job): bool => $job->tenantIds === [$tenant->id]
    );

    expect(session('inertia.flash_data.toast'))
        ->type->toBe('success')
        ->message->toContain(__('app.tenant.products.images.summary_queued', ['count' => 1]));
});
