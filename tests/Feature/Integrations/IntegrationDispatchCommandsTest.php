<?php

use App\Console\Commands\Integrations\LinkSalesProductsCommand;
use App\Jobs\Cleanup\CleanupOldSalesJob;
use App\Jobs\Cleanup\CleanupOrphanSalesJob;
use App\Jobs\Cleanup\DeactivateInactiveProductsJob;
use App\Jobs\Cleanup\RestoreSoldProductsJob;
use App\Jobs\Integrations\Dispatch\DispatchTenantIntegrationDailySyncJob;
use App\Jobs\Integrations\Dispatch\DispatchTenantIntegrationInitialSyncJob;
use App\Jobs\Integrations\Maintenance\RecalculateTenantMonthlySalesSummariesJob;
use App\Jobs\Integrations\Maintenance\RunTenantIntegrationNightlyMaintenanceJob;
use App\Jobs\Integrations\Maintenance\RunTenantIntegrationPostSyncJob;
use App\Jobs\Integrations\Products\SyncTenantProductsDayJob;
use App\Jobs\Integrations\Sales\SyncTenantSalesDayJob;
use App\Models\IntegrationSyncDay;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\Integrations\Contracts\SalesIntegrationService;
use App\Services\Integrations\Orchestration\DispatchDailySyncService;
use App\Services\Integrations\Orchestration\DispatchInitialSyncService;
use App\Services\Integrations\Support\DeterministicIdGenerator;
use App\Services\Integrations\Support\IntegrationServiceResolver;
use App\Services\Integrations\Support\RecalculateMonthlySalesSummariesService;
use App\Services\Integrations\Support\SyncSalesProductReferencesService;
use App\Services\Integrations\Support\TenantIntegrationConfigNormalizer;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

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

    $this->actingAs(User::factory()->create());
});

test('integrations dispatch daily command enqueues jobs for active integrations', function () {
    Bus::fake();
    Notification::fake();
    $tenantDatabase = tempnam(sys_get_temp_dir(), 'tenant_daily_notification_');
    config([
        'multitenancy.tenant_database_connection_name' => 'tenant',
        'database.connections.tenant' => array_merge(config('database.connections.sqlite'), [
            'database' => $tenantDatabase,
        ]),
    ]);

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Daily',
        'slug' => 'tenant-daily-'.fake()->numberBetween(100, 999),
        'database' => $tenantDatabase,
        'status' => 'active',
    ]));
    $tenantUser = $tenant->execute(function (): User {
        if (! Schema::connection('tenant')->hasTable('users')) {
            Schema::connection('tenant')->create('users', function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->text('two_factor_secret')->nullable();
                $table->text('two_factor_recovery_codes')->nullable();
                $table->timestamp('two_factor_confirmed_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        return User::factory()->create(['is_active' => true]);
    });

    TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => 'sysmo',
        'http_method' => 'POST',
        'api_url' => 'https://sysmo.example.com',
        'config' => ['processing' => ['daily_lookback_days' => 7]],
        'is_active' => true,
    ]);

    $this->artisan('integrations:dispatch-daily')->assertSuccessful();

    Bus::assertDispatched(DispatchTenantIntegrationDailySyncJob::class, 1);
    Notification::assertSentTo(
        $tenantUser,
        AppNotification::class,
        fn (AppNotification $notification): bool => $notification->title === 'Sincronização diária iniciada'
            && str_contains($notification->message, (string) $tenant->id)
            && $notification->type === 'info'
    );
});

test('integrations dispatch initial command enqueues jobs for active integrations', function () {
    Bus::fake();

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Initial',
        'slug' => 'tenant-initial-'.fake()->numberBetween(100, 999),
        'database' => (string) config('database.connections.mysql.database'),
        'status' => 'active',
    ]));

    TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => 'sysmo',
        'http_method' => 'POST',
        'api_url' => 'https://sysmo.example.com',
        'config' => ['processing' => ['sales_initial_days' => 30, 'products_initial_days' => 15]],
        'is_active' => true,
    ]);

    $this->artisan('integrations:dispatch-initial')->assertSuccessful();

    Bus::assertDispatched(DispatchTenantIntegrationInitialSyncJob::class, 1);
});

test('integrations dispatch initial command supports resource option', function () {
    Bus::fake();

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Initial Resource',
        'slug' => 'tenant-initial-resource-'.fake()->numberBetween(100, 999),
        'database' => (string) config('database.connections.mysql.database'),
        'status' => 'active',
    ]));

    TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => 'sysmo',
        'http_method' => 'POST',
        'api_url' => 'https://sysmo.example.com',
        'config' => ['processing' => ['sales_initial_days' => 30, 'products_initial_days' => 15]],
        'is_active' => true,
    ]);

    $this->artisan('integrations:dispatch-initial --resource=sales')->assertSuccessful();

    Bus::assertDispatched(
        DispatchTenantIntegrationInitialSyncJob::class,
        fn (DispatchTenantIntegrationInitialSyncJob $job): bool => $job->resource === 'sales'
            && $job->ignoreSyncDaysCheck === false
    );
});

test('integrations dispatch initial command supports ignore synced days option', function () {
    Bus::fake();

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Initial Ignore Synced',
        'slug' => 'tenant-initial-ignore-synced-'.fake()->numberBetween(100, 999),
        'database' => (string) config('database.connections.mysql.database'),
        'status' => 'active',
    ]));

    TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => 'sysmo',
        'http_method' => 'POST',
        'api_url' => 'https://sysmo.example.com',
        'config' => ['processing' => ['sales_initial_days' => 30, 'products_initial_days' => 15]],
        'is_active' => true,
    ]);

    $this->artisan('integrations:dispatch-initial --ignore-synced-days')->assertSuccessful();

    Bus::assertDispatched(
        DispatchTenantIntegrationInitialSyncJob::class,
        fn (DispatchTenantIntegrationInitialSyncJob $job): bool => $job->resource === null
            && $job->ignoreSyncDaysCheck === true
    );
});

test('integrations dispatch daily command supports resource option', function () {
    Bus::fake();
    Notification::fake();
    $tenantDatabase = tempnam(sys_get_temp_dir(), 'tenant_daily_resource_');
    config([
        'multitenancy.tenant_database_connection_name' => 'tenant',
        'database.connections.tenant' => array_merge(config('database.connections.sqlite'), [
            'database' => $tenantDatabase,
        ]),
    ]);

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Daily Resource',
        'slug' => 'tenant-daily-resource-'.fake()->numberBetween(100, 999),
        'database' => $tenantDatabase,
        'status' => 'active',
    ]));

    $tenant->execute(function (): void {
        if (! Schema::connection('tenant')->hasTable('users')) {
            Schema::connection('tenant')->create('users', function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->text('two_factor_secret')->nullable();
                $table->text('two_factor_recovery_codes')->nullable();
                $table->timestamp('two_factor_confirmed_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        User::factory()->create(['is_active' => true]);
    });

    TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => 'sysmo',
        'http_method' => 'POST',
        'api_url' => 'https://sysmo.example.com',
        'config' => ['processing' => ['daily_lookback_days' => 7]],
        'is_active' => true,
    ]);

    $this->artisan('integrations:dispatch-daily --resource=products')->assertSuccessful();

    Bus::assertDispatched(
        DispatchTenantIntegrationDailySyncJob::class,
        fn (DispatchTenantIntegrationDailySyncJob $job): bool => $job->resource === 'products'
    );
});

test('dispatch commands fail for invalid resource option', function () {
    $this->artisan('integrations:dispatch-initial --resource=invalid')->assertFailed();
    $this->artisan('integrations:dispatch-daily --resource=invalid')->assertFailed();
});

test('initial sync sales jobs are queued with tenant context', function () {
    config([
        'queue.default' => 'database',
        'queue.connections.database.connection' => 'landlord',
        'multitenancy.tenant_database_connection_name' => 'tenant',
    ]);

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Initial Context',
        'slug' => 'tenant-initial-context-'.fake()->numberBetween(100, 999),
        'database' => (string) config('database.connections.mysql.database'),
        'status' => 'active',
    ]));

    $integration = TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => 'sysmo',
        'http_method' => 'POST',
        'api_url' => 'https://sysmo.example.com',
        'config' => ['processing' => ['sales_initial_days' => 1, 'products_initial_days' => 1]],
        'is_active' => true,
    ]);

    app(DispatchInitialSyncService::class)->dispatch($integration);

    $payload = DB::connection('landlord')
        ->table('jobs')
        ->where('payload', 'like', '%SyncTenantSalesDayJob%')
        ->value('payload');

    $decodedPayload = json_decode((string) $payload, true);

    expect($payload)->not->toBeNull()
        ->and($decodedPayload)->toHaveKey('illuminate:log:context')
        ->and(unserialize($decodedPayload['illuminate:log:context']['data']['tenantId']))->toBe($tenant->id);
});

test('daily sync sales jobs are queued with tenant context', function () {
    config([
        'queue.default' => 'database',
        'queue.connections.database.connection' => 'landlord',
        'multitenancy.tenant_database_connection_name' => 'tenant',
    ]);

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Daily Context',
        'slug' => 'tenant-daily-context-'.fake()->numberBetween(100, 999),
        'database' => (string) config('database.connections.mysql.database'),
        'status' => 'active',
    ]));

    $integration = TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => 'sysmo',
        'http_method' => 'POST',
        'api_url' => 'https://sysmo.example.com',
        'config' => ['processing' => ['daily_lookback_days' => 2]],
        'is_active' => true,
    ]);

    app(DispatchDailySyncService::class)->dispatch($integration);

    $payload = DB::connection('landlord')
        ->table('jobs')
        ->where('payload', 'like', '%SyncTenantSalesDayJob%')
        ->value('payload');

    $decodedPayload = json_decode((string) $payload, true);

    expect($payload)->not->toBeNull()
        ->and($decodedPayload)->toHaveKey('illuminate:log:context')
        ->and(unserialize($decodedPayload['illuminate:log:context']['data']['tenantId']))->toBe($tenant->id);
});

test('daily sync chains post sync maintenance after sales and products jobs', function () {
    Bus::fake();

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Daily Post Sync',
        'slug' => 'tenant-daily-post-sync-'.fake()->numberBetween(100, 999),
        'database' => (string) config('database.connections.mysql.database'),
        'status' => 'active',
    ]));

    $integration = TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => 'sysmo',
        'http_method' => 'POST',
        'api_url' => 'https://sysmo.example.com',
        'config' => ['processing' => ['daily_lookback_days' => 2]],
        'is_active' => true,
    ]);

    $yesterday = Carbon::yesterday()->toDateString();
    $previousDay = Carbon::yesterday()->subDay()->toDateString();

    app(DispatchDailySyncService::class)->dispatch($integration);

    Bus::assertChained([
        new SyncTenantSalesDayJob((string) $integration->id, $previousDay),
        new SyncTenantSalesDayJob((string) $integration->id, $yesterday),
        new SyncTenantProductsDayJob((string) $integration->id, $previousDay),
        new SyncTenantProductsDayJob((string) $integration->id, $yesterday),
        new RunTenantIntegrationPostSyncJob((string) $tenant->id),
    ]);
});

test('initial sync skips sales dates already marked as success', function () {
    Bus::fake();

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Initial Skip',
        'slug' => 'tenant-initial-skip-'.fake()->numberBetween(100, 999),
        'database' => (string) config('database.connections.mysql.database'),
        'status' => 'active',
    ]));

    $integration = TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => 'sysmo',
        'http_method' => 'POST',
        'api_url' => 'https://sysmo.example.com',
        'config' => ['processing' => ['sales_initial_days' => 1, 'products_initial_days' => 1]],
        'is_active' => true,
    ]);

    $referenceDate = Carbon::yesterday()->toDateString();

    IntegrationSyncDay::query()->create([
        'tenant_integration_id' => $integration->id,
        'resource' => 'sales',
        'reference_date' => $referenceDate,
        'status' => 'success',
    ]);

    IntegrationSyncDay::query()->create([
        'tenant_integration_id' => $integration->id,
        'resource' => 'products',
        'reference_date' => $referenceDate,
        'status' => 'success',
    ]);

    app(DispatchInitialSyncService::class)->dispatch($integration);

    Bus::assertNotDispatched(SyncTenantSalesDayJob::class);
    Bus::assertNotDispatched(SyncTenantProductsDayJob::class);
});

test('initial sync can ignore synced days check when requested', function () {
    Bus::fake();

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Initial Ignore Check',
        'slug' => 'tenant-initial-ignore-check-'.fake()->numberBetween(100, 999),
        'database' => (string) config('database.connections.mysql.database'),
        'status' => 'active',
    ]));

    $integration = TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => 'sysmo',
        'http_method' => 'POST',
        'api_url' => 'https://sysmo.example.com',
        'config' => ['processing' => ['sales_initial_days' => 1, 'products_initial_days' => 1]],
        'is_active' => true,
    ]);

    $referenceDate = Carbon::yesterday()->toDateString();

    IntegrationSyncDay::query()->create([
        'tenant_integration_id' => $integration->id,
        'resource' => 'sales',
        'reference_date' => $referenceDate,
        'status' => 'success',
    ]);

    IntegrationSyncDay::query()->create([
        'tenant_integration_id' => $integration->id,
        'resource' => 'products',
        'reference_date' => $referenceDate,
        'status' => 'success',
    ]);

    app(DispatchInitialSyncService::class)->dispatch(
        integration: $integration,
        ignoreSyncDaysCheck: true,
    );

    Bus::assertChained([
        new SyncTenantSalesDayJob((string) $integration->id, $referenceDate, true),
        new SyncTenantProductsDayJob((string) $integration->id, $referenceDate, true),
        new RunTenantIntegrationPostSyncJob((string) $tenant->id),
    ]);
});

test('initial sync chains post sync maintenance after initial jobs', function () {
    Bus::fake();

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Initial Post Sync',
        'slug' => 'tenant-initial-post-sync-'.fake()->numberBetween(100, 999),
        'database' => (string) config('database.connections.mysql.database'),
        'status' => 'active',
    ]));

    $integration = TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => 'sysmo',
        'http_method' => 'POST',
        'api_url' => 'https://sysmo.example.com',
        'config' => ['processing' => ['sales_initial_days' => 1, 'products_initial_days' => 1]],
        'is_active' => true,
    ]);

    $yesterday = Carbon::yesterday()->toDateString();

    app(DispatchInitialSyncService::class)->dispatch($integration);

    Bus::assertChained([
        new SyncTenantSalesDayJob((string) $integration->id, $yesterday, true),
        new SyncTenantProductsDayJob((string) $integration->id, $yesterday, true),
        new RunTenantIntegrationPostSyncJob((string) $tenant->id),
    ]);
});

test('monthly sales recalculate command dispatches one job per tenant by default', function () {
    Bus::fake();

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Monthly Dispatch',
        'slug' => 'tenant-monthly-dispatch-'.fake()->numberBetween(100, 999),
        'database' => (string) config('database.connections.mysql.database'),
        'status' => 'active',
    ]));

    $this->artisan('monthly-sales:recalculate --month=2025-01')
        ->assertSuccessful();

    Bus::assertDispatched(
        RecalculateTenantMonthlySalesSummariesJob::class,
        fn (RecalculateTenantMonthlySalesSummariesJob $job): bool => $job->tenantId === (string) $tenant->id
            && $job->month === '2025-01'
    );
});

test('monthly sales recalculate command can run synchronously for manual execution', function () {
    Bus::fake();

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Monthly Sync',
        'slug' => 'tenant-monthly-sync-'.fake()->numberBetween(100, 999),
        'database' => (string) config('database.connections.mysql.database'),
        'status' => 'active',
    ]));

    $service = Mockery::mock(RecalculateMonthlySalesSummariesService::class);
    $service->shouldReceive('recalculate')
        ->once()
        ->with(Mockery::on(fn (Tenant $selectedTenant): bool => $selectedTenant->is($tenant)), '2025-01')
        ->andReturn([
            'tenant_id' => (string) $tenant->id,
            'tenant_name' => $tenant->name,
            'sales_linked' => 1,
            'deleted' => 2,
            'inserted' => 3,
            'summaries_linked' => 4,
        ]);

    app()->instance(RecalculateMonthlySalesSummariesService::class, $service);

    $this->artisan(sprintf('monthly-sales:recalculate --tenant=%s --month=2025-01 --sync', $tenant->id))
        ->assertSuccessful();

    Bus::assertNotDispatched(RecalculateTenantMonthlySalesSummariesJob::class);
});

test('monthly sales recalculate service links sales and monthly summaries by codigo erp', function () {
    config([
        'multitenancy.switch_tenant_tasks' => [],
        'multitenancy.tenant_database_connection_name' => null,
    ]);

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Monthly Service',
        'slug' => 'tenant-monthly-service-'.fake()->numberBetween(100, 999),
        'database' => (string) config('database.connections.'.config('database.default').'.database'),
        'status' => 'active',
    ]));

    $tenantConnectionName = (string) (config('multitenancy.tenant_database_connection_name') ?: config('database.default'));
    $now = now();
    $generator = app(DeterministicIdGenerator::class);
    $productId = $generator->productId((string) $tenant->id, '7891234500993', '99501');

    DB::connection($tenantConnectionName)->table('products')->insert([
        'id' => $productId,
        'tenant_id' => (string) $tenant->id,
        'name' => 'Produto Monthly Service',
        'ean' => '7891234500993',
        'codigo_erp' => '99501',
        'description' => null,
        'brand' => null,
        'unit_measure' => null,
        'sales_status' => null,
        'status' => 'synced',
        'sync_source' => 'manual',
        'sync_at' => $now,
        'deleted_at' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::connection($tenantConnectionName)->table('sales')->insert([
        'id' => $generator->saleId(
            tenantId: (string) $tenant->id,
            integrationId: (string) str()->ulid(),
            storeDocument: '81342172000145',
            codigoErp: '99501',
            saleDate: '2025-01-23',
            promotion: 'N',
        ),
        'tenant_id' => (string) $tenant->id,
        'store_id' => null,
        'product_id' => null,
        'ean' => null,
        'codigo_erp' => '99501',
        'acquisition_cost' => 10,
        'sale_price' => 15,
        'sale_date' => '2025-01-23',
        'promotion' => 'N',
        'total_sale_quantity' => 2,
        'total_sale_value' => 30,
        'total_profit_margin' => 5,
        'margem_contribuicao' => 8,
        'extra_data' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $summary = app(RecalculateMonthlySalesSummariesService::class)->recalculate($tenant, '2025-01');
    $linkedSale = DB::connection($tenantConnectionName)->table('sales')->where('tenant_id', (string) $tenant->id)->first();
    $monthlySummary = DB::connection($tenantConnectionName)->table('monthly_sales_summaries')->where('tenant_id', (string) $tenant->id)->first();

    expect($summary['inserted'])->toBe(1)
        ->and($linkedSale?->product_id)->toBe($productId)
        ->and($linkedSale?->ean)->toBe('7891234500993')
        ->and($monthlySummary?->product_id)->toBe($productId)
        ->and($monthlySummary?->ean)->toBe('7891234500993')
        ->and((int) $monthlySummary?->total_sale_quantity)->toBe(2);
});

test('post sync maintenance job runs cleanup ean reference sync link sales and dispatches monthly recalc for tenant', function () {
    Bus::fake();
    $tenantId = (string) str()->ulid();

    Artisan::shouldReceive('call')
        ->once()
        ->with('sync:cleanup', ['--tenant' => $tenantId, '--all' => true])
        ->andReturn(0);

    Artisan::shouldReceive('call')
        ->once()
        ->with('sync:products-from-ean-references', ['--tenant' => $tenantId])
        ->andReturn(0);

    Artisan::shouldReceive('call')
        ->once()
        ->with('sync:link-sales', ['--tenant' => $tenantId])
        ->andReturn(0);

    (new RunTenantIntegrationPostSyncJob($tenantId))->handle();

    Bus::assertDispatched(
        RecalculateTenantMonthlySalesSummariesJob::class,
        fn (RecalculateTenantMonthlySalesSummariesJob $job): bool => $job->tenantId === $tenantId
            && $job->month === null
    );
});

test('sales day job only syncs sales and leaves product linking to command', function () {
    config(['multitenancy.tenant_database_connection_name' => null]);

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Sales Final Link',
        'slug' => 'tenant-sales-final-link-'.fake()->numberBetween(100, 999),
        'database' => 'tenant_sales_final_link',
        'status' => 'active',
    ]));

    $integration = TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => 'sysmo',
        'http_method' => 'POST',
        'api_url' => 'https://sysmo.example.com',
        'config' => ['processing' => ['sales_page_size' => 20000]],
        'is_active' => true,
    ]);

    $firstStore = Store::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Loja 1',
        'document' => '11111111000191',
        'code' => '1',
        'status' => 'published',
    ]);

    $secondStore = Store::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Loja 2',
        'document' => '22222222000191',
        'code' => '2',
        'status' => 'published',
    ]);

    $salesService = new class($firstStore->id, $secondStore->id) implements SalesIntegrationService
    {
        /** @var array<int, array<string, mixed>> */
        public array $filters = [];

        public function __construct(
            private readonly string $firstStoreId,
            private readonly string $secondStoreId,
        ) {}

        public function fetchSales(TenantIntegration $integration, array $filters = []): array
        {
            $this->filters[] = $filters;

            if (($filters['store_id'] ?? null) === $this->firstStoreId) {
                return [
                    ['codigo_erp' => '10001'],
                    ['codigo_erp' => '10002'],
                ];
            }

            if (($filters['store_id'] ?? null) === $this->secondStoreId) {
                return [
                    ['codigo_erp' => '10002'],
                    ['product_code' => '10003'],
                ];
            }

            return [];
        }
    };

    $resolver = Mockery::mock(IntegrationServiceResolver::class);
    $resolver->shouldReceive('resolveSalesService')
        ->once()
        ->with(Mockery::type(TenantIntegration::class))
        ->andReturn($salesService);

    $syncSalesProductReferencesService = Mockery::mock(SyncSalesProductReferencesService::class);
    $syncSalesProductReferencesService->shouldNotReceive('syncByCodigoErp');
    app()->instance(SyncSalesProductReferencesService::class, $syncSalesProductReferencesService);

    $handleMethod = new ReflectionMethod(SyncTenantSalesDayJob::class, 'handle');

    expect($handleMethod->getNumberOfParameters())->toBe(2);

    app()->call([new SyncTenantSalesDayJob((string) $integration->id, '2025-01-22', true), 'handle'], [
        'integrationServiceResolver' => $resolver,
        'configNormalizer' => app(TenantIntegrationConfigNormalizer::class),
    ]);

    expect($salesService->filters)->toHaveCount(2);
});

test('initial sync dispatches products as single full sync bootstrap', function () {
    Bus::fake();

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Products Bootstrap',
        'slug' => 'tenant-products-bootstrap-'.fake()->numberBetween(100, 999),
        'database' => (string) config('database.connections.mysql.database'),
        'status' => 'active',
    ]));

    $integration = TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => 'sysmo',
        'http_method' => 'POST',
        'api_url' => 'https://sysmo.example.com',
        'config' => ['processing' => ['sales_initial_days' => 3, 'products_initial_days' => 30]],
        'is_active' => true,
    ]);

    app(DispatchInitialSyncService::class)->dispatch($integration);

    $yesterday = Carbon::yesterday()->toDateString();
    $twoDaysAgo = Carbon::yesterday()->subDays(2)->toDateString();
    $previousDay = Carbon::yesterday()->subDay()->toDateString();

    Bus::assertChained([
        new SyncTenantSalesDayJob((string) $integration->id, $twoDaysAgo, true),
        new SyncTenantSalesDayJob((string) $integration->id, $previousDay, true),
        new SyncTenantSalesDayJob((string) $integration->id, $yesterday, true),
        new SyncTenantProductsDayJob((string) $integration->id, $yesterday, true),
        new RunTenantIntegrationPostSyncJob((string) $tenant->id),
    ]);
});

test('queue and cache infrastructure use landlord connection explicitly', function () {
    expect(config('multitenancy.tenant_database_connection_name'))->toBe('tenant')
        ->and(config('cache.stores.database.connection'))->toBe('landlord')
        ->and(config('cache.stores.database.lock_connection'))->toBe('landlord')
        ->and(config('queue.connections.database.connection'))->toBe('landlord')
        ->and(config('queue.batching.database'))->toBe('landlord')
        ->and(config('queue.failed.database'))->toBe('landlord');
});

test('makeCurrent switches only dedicated tenant connection', function () {
    $defaultConnectionName = (string) config('database.default');
    $tenantConnectionName = (string) config('multitenancy.tenant_database_connection_name');

    $originalDefaultDatabase = config("database.connections.{$defaultConnectionName}.database");
    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Connection Switch',
        'slug' => 'tenant-connection-switch-'.fake()->numberBetween(100, 999),
        'database' => 'tenant_connection_switch_db',
        'status' => 'active',
    ]));

    $tenant->makeCurrent();

    expect(config("database.connections.{$tenantConnectionName}.database"))->toBe('tenant_connection_switch_db')
        ->and(config("database.connections.{$defaultConnectionName}.database"))->toBe($originalDefaultDatabase);

    Tenant::forgetCurrent();

    expect(config("database.connections.{$tenantConnectionName}.database"))->toBeNull();
});

test('integrations nightly maintenance command enqueues maintenance job', function () {
    Bus::fake();

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Maintenance',
        'slug' => 'tenant-maintenance-'.fake()->numberBetween(100, 999),
        'database' => (string) config('database.connections.mysql.database'),
        'status' => 'active',
    ]));

    TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => 'sysmo',
        'http_method' => 'POST',
        'api_url' => 'https://sysmo.example.com',
        'config' => ['processing' => ['sales_retention_days' => 120]],
        'is_active' => true,
    ]);

    $this->artisan('integrations:dispatch-nightly-maintenance')->assertSuccessful();

    Bus::assertDispatched(RunTenantIntegrationNightlyMaintenanceJob::class, 1);
});

test('slow product reconciliation entrypoints are removed', function () {
    expect(Artisan::all())->not->toHaveKey('integrations:reconcile-sales-products')
        ->and(class_exists('App\\Console\\Commands\\Integrations\\ReconcileSalesProductsCommand'))->toBeFalse()
        ->and(class_exists('App\\Jobs\\Integrations\\Products\\FinalizeTenantProductsSyncJob'))->toBeFalse()
        ->and(class_exists('App\\Jobs\\Integrations\\Products\\ReconcileProductsFromEanReferencesChunkJob'))->toBeFalse()
        ->and(class_exists('App\\Jobs\\Integrations\\Products\\ReconcileSalesProductsChunkJob'))->toBeFalse();
});

test('link sales products command uses tenant option without client context', function () {
    Notification::fake();
    config(['multitenancy.tenant_database_connection_name' => null]);

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Link Sales',
        'slug' => 'tenant-link-sales-'.fake()->numberBetween(100, 999),
        'database' => 'tenant_link_sales',
        'status' => 'active',
    ]));

    $otherTenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Other Tenant Link Sales',
        'slug' => 'other-tenant-link-sales-'.fake()->numberBetween(100, 999),
        'database' => 'other_tenant_link_sales',
        'status' => 'active',
    ]));

    $tenantConnectionName = (string) (config('multitenancy.tenant_database_connection_name') ?: config('database.default'));
    $now = now();
    $generator = app(DeterministicIdGenerator::class);

    $productId = $generator->productId(
        tenantId: (string) $tenant->id,
        ean: '7891234500016',
        codigoErp: '99801',
    );

    DB::connection($tenantConnectionName)->table('products')->insert([
        [
            'id' => $productId,
            'tenant_id' => (string) $tenant->id,
            'name' => 'Produto Link Sales',
            'ean' => '7891234500016',
            'codigo_erp' => '99801',
            'description' => null,
            'brand' => null,
            'unit_measure' => null,
            'sales_status' => null,
            'status' => 'synced',
            'sync_source' => 'manual',
            'sync_at' => $now,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => $generator->productId(
                tenantId: (string) $otherTenant->id,
                ean: '7891234500016',
                codigoErp: '99801',
            ),
            'tenant_id' => (string) $otherTenant->id,
            'name' => 'Produto Outro Tenant',
            'ean' => '7891234500016',
            'codigo_erp' => '99801',
            'description' => null,
            'brand' => null,
            'unit_measure' => null,
            'sales_status' => null,
            'status' => 'synced',
            'sync_source' => 'manual',
            'sync_at' => $now,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    DB::connection($tenantConnectionName)->table('sales')->insert([
        [
            'id' => $generator->saleId(
                tenantId: (string) $tenant->id,
                integrationId: (string) str()->ulid(),
                storeDocument: '81342172000145',
                codigoErp: '99801',
                saleDate: '2025-01-23',
                promotion: 'N',
            ),
            'tenant_id' => (string) $tenant->id,
            'store_id' => null,
            'product_id' => null,
            'ean' => null,
            'codigo_erp' => '99801',
            'acquisition_cost' => 1,
            'sale_price' => 1,
            'sale_date' => '2025-01-23',
            'promotion' => 'N',
            'total_sale_quantity' => 1,
            'total_sale_value' => 1,
            'total_profit_margin' => 1,
            'margem_contribuicao' => 1,
            'extra_data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => $generator->saleId(
                tenantId: (string) $otherTenant->id,
                integrationId: (string) str()->ulid(),
                storeDocument: '81342172000145',
                codigoErp: '99801',
                saleDate: '2025-01-23',
                promotion: 'N',
            ),
            'tenant_id' => (string) $otherTenant->id,
            'store_id' => null,
            'product_id' => null,
            'ean' => null,
            'codigo_erp' => '99801',
            'acquisition_cost' => 1,
            'sale_price' => 1,
            'sale_date' => '2025-01-23',
            'promotion' => 'N',
            'total_sale_quantity' => 1,
            'total_sale_value' => 1,
            'total_profit_margin' => 1,
            'margem_contribuicao' => 1,
            'extra_data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    $this->artisan(sprintf('sync:link-sales --tenant=%s', $tenant->id))
        ->assertSuccessful();

    $linkedSale = DB::connection($tenantConnectionName)->table('sales')
        ->where('tenant_id', (string) $tenant->id)
        ->where('codigo_erp', '99801')
        ->first();

    $untouchedSale = DB::connection($tenantConnectionName)->table('sales')
        ->where('tenant_id', (string) $otherTenant->id)
        ->where('codigo_erp', '99801')
        ->first();

    expect($linkedSale?->product_id)->toBe($productId)
        ->and($linkedSale?->ean)->toBe('7891234500016')
        ->and($untouchedSale?->product_id)->toBeNull()
        ->and($untouchedSale?->ean)->toBeNull();
});

test('link sales products command uses mysql update join syntax', function () {
    $tenantId = '01kqaxz6d322qnsapct4q5cvbx';
    $connection = Mockery::mock(Connection::class);

    $connection->shouldReceive('getDriverName')
        ->once()
        ->andReturn('mysql');

    $connection->shouldReceive('affectingStatement')
        ->once()
        ->with(
            Mockery::on(fn (string $sql): bool => str_contains($sql, 'UPDATE sales')
                && str_contains($sql, 'INNER JOIN products p')
                && ! str_contains($sql, 'FROM products p')),
            [$tenantId],
        )
        ->andReturn(10);

    DB::shouldReceive('connection')
        ->once()
        ->with('tenant')
        ->andReturn($connection);

    $method = new ReflectionMethod(LinkSalesProductsCommand::class, 'linkSalesToProducts');
    $method->setAccessible(true);

    $updated = $method->invoke(new LinkSalesProductsCommand, 'tenant', $tenantId);

    expect($updated)->toBe(10);
});

test('cleanup command dispatches tenant cleanup jobs without client context', function () {
    Bus::fake();
    Notification::fake();
    config(['multitenancy.tenant_database_connection_name' => null]);

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Cleanup',
        'slug' => 'tenant-cleanup-'.fake()->numberBetween(100, 999),
        'database' => 'tenant_cleanup',
        'status' => 'active',
    ]));

    TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => 'sysmo',
        'http_method' => 'POST',
        'api_url' => 'https://sysmo.example.com',
        'config' => ['processing' => ['sales_retention_days' => 30]],
        'is_active' => true,
    ]);

    $tenantConnectionName = (string) (config('multitenancy.tenant_database_connection_name') ?: config('database.default'));
    $now = now();
    $generator = app(DeterministicIdGenerator::class);

    $deletedProductId = $generator->productId(
        tenantId: (string) $tenant->id,
        ean: '7891234500108',
        codigoErp: '99704',
    );

    DB::connection($tenantConnectionName)->table('products')->insert([
        [
            'id' => $generator->productId(
                tenantId: (string) $tenant->id,
                ean: '7891234500101',
                codigoErp: '99703',
            ),
            'tenant_id' => (string) $tenant->id,
            'name' => 'Produto Inativo Cleanup',
            'ean' => '7891234500101',
            'codigo_erp' => '99703',
            'description' => null,
            'brand' => null,
            'unit_measure' => null,
            'sales_status' => null,
            'status' => 'synced',
            'sync_source' => 'manual',
            'sync_at' => $now,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => $deletedProductId,
            'tenant_id' => (string) $tenant->id,
            'name' => 'Produto Deletado Com Venda',
            'ean' => '7891234500108',
            'codigo_erp' => '99704',
            'description' => null,
            'brand' => null,
            'unit_measure' => null,
            'sales_status' => null,
            'status' => 'synced',
            'sync_source' => 'manual',
            'sync_at' => $now,
            'deleted_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    DB::connection($tenantConnectionName)->table('sales')->insert([
        [
            'id' => $generator->saleId(
                tenantId: (string) $tenant->id,
                integrationId: (string) str()->ulid(),
                storeDocument: '81342172000145',
                codigoErp: '99701',
                saleDate: now()->toDateString(),
                promotion: 'N',
            ),
            'tenant_id' => (string) $tenant->id,
            'store_id' => null,
            'product_id' => (string) str()->ulid(),
            'ean' => '7891234500102',
            'codigo_erp' => '99701',
            'acquisition_cost' => 1,
            'sale_price' => 1,
            'sale_date' => now()->toDateString(),
            'promotion' => 'N',
            'total_sale_quantity' => 1,
            'total_sale_value' => 1,
            'total_profit_margin' => 1,
            'margem_contribuicao' => 1,
            'extra_data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => $generator->saleId(
                tenantId: (string) $tenant->id,
                integrationId: (string) str()->ulid(),
                storeDocument: '81342172000145',
                codigoErp: '99702',
                saleDate: now()->subDays(45)->toDateString(),
                promotion: 'N',
            ),
            'tenant_id' => (string) $tenant->id,
            'store_id' => null,
            'product_id' => null,
            'ean' => '7891234500103',
            'codigo_erp' => '99702',
            'acquisition_cost' => 1,
            'sale_price' => 1,
            'sale_date' => now()->subDays(45)->toDateString(),
            'promotion' => 'N',
            'total_sale_quantity' => 1,
            'total_sale_value' => 1,
            'total_profit_margin' => 1,
            'margem_contribuicao' => 1,
            'extra_data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => $generator->saleId(
                tenantId: (string) $tenant->id,
                integrationId: (string) str()->ulid(),
                storeDocument: '81342172000145',
                codigoErp: '99704',
                saleDate: now()->toDateString(),
                promotion: 'N',
            ),
            'tenant_id' => (string) $tenant->id,
            'store_id' => null,
            'product_id' => $deletedProductId,
            'ean' => '7891234500108',
            'codigo_erp' => '99704',
            'acquisition_cost' => 1,
            'sale_price' => 1,
            'sale_date' => now()->toDateString(),
            'promotion' => 'N',
            'total_sale_quantity' => 1,
            'total_sale_value' => 1,
            'total_profit_margin' => 1,
            'margem_contribuicao' => 1,
            'extra_data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    $this->artisan(sprintf('sync:cleanup --tenant=%s --all', $tenant->id))
        ->assertSuccessful();

    Bus::assertChained([
        CleanupOrphanSalesJob::class,
        CleanupOldSalesJob::class,
        DeactivateInactiveProductsJob::class,
        RestoreSoldProductsJob::class,
    ]);
});

test('cleanup jobs only mutate records for the given tenant', function () {
    config(['multitenancy.tenant_database_connection_name' => null]);

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Cleanup Jobs',
        'slug' => 'tenant-cleanup-jobs-'.fake()->numberBetween(100, 999),
        'database' => 'tenant_cleanup_jobs',
        'status' => 'active',
    ]));

    $otherTenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Other Tenant Cleanup Jobs',
        'slug' => 'other-tenant-cleanup-jobs-'.fake()->numberBetween(100, 999),
        'database' => 'other_tenant_cleanup_jobs',
        'status' => 'active',
    ]));

    $tenantConnectionName = (string) (config('multitenancy.tenant_database_connection_name') ?: config('database.default'));
    $now = now();
    $generator = app(DeterministicIdGenerator::class);

    $tenantOldSaleId = $generator->saleId((string) $tenant->id, (string) str()->ulid(), '81342172000145', '99601', '2025-01-24', 'N');
    $otherOldSaleId = $generator->saleId((string) $otherTenant->id, (string) str()->ulid(), '81342172000145', '99601', '2025-01-24', 'N');
    $tenantOrphanSaleId = $generator->saleId((string) $tenant->id, (string) str()->ulid(), '81342172000145', '99602', '2025-01-24', 'N');
    $otherOrphanSaleId = $generator->saleId((string) $otherTenant->id, (string) str()->ulid(), '81342172000145', '99602', '2025-01-24', 'N');
    $tenantInactiveProductId = $generator->productId((string) $tenant->id, '7891234500201', '99603');
    $otherInactiveProductId = $generator->productId((string) $otherTenant->id, '7891234500201', '99603');
    $tenantDeletedProductId = $generator->productId((string) $tenant->id, '7891234500208', '99604');
    $otherDeletedProductId = $generator->productId((string) $otherTenant->id, '7891234500208', '99604');

    DB::connection($tenantConnectionName)->table('sales')->insert(collect([
        [$tenantOldSaleId, (string) $tenant->id, '99601', null],
        [$otherOldSaleId, (string) $otherTenant->id, '99601', null],
        [$tenantOrphanSaleId, (string) $tenant->id, '99602', (string) str()->ulid()],
        [$otherOrphanSaleId, (string) $otherTenant->id, '99602', (string) str()->ulid()],
    ])->map(fn (array $sale): array => [
        'id' => $sale[0],
        'tenant_id' => $sale[1],
        'store_id' => null,
        'product_id' => $sale[3],
        'ean' => null,
        'codigo_erp' => $sale[2],
        'acquisition_cost' => 1,
        'sale_price' => 1,
        'sale_date' => '2025-01-24',
        'promotion' => 'N',
        'total_sale_quantity' => 1,
        'total_sale_value' => 1,
        'total_profit_margin' => 1,
        'margem_contribuicao' => 1,
        'extra_data' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ])->all());

    DB::connection($tenantConnectionName)->table('products')->insert(collect([
        [$tenantInactiveProductId, (string) $tenant->id, '7891234500201', '99603', null],
        [$otherInactiveProductId, (string) $otherTenant->id, '7891234500201', '99603', null],
        [$tenantDeletedProductId, (string) $tenant->id, '7891234500208', '99604', $now],
        [$otherDeletedProductId, (string) $otherTenant->id, '7891234500208', '99604', $now],
    ])->map(fn (array $product): array => [
        'id' => $product[0],
        'tenant_id' => $product[1],
        'name' => 'Produto Cleanup Job',
        'ean' => $product[2],
        'codigo_erp' => $product[3],
        'description' => null,
        'brand' => null,
        'unit_measure' => null,
        'sales_status' => null,
        'status' => 'synced',
        'sync_source' => 'manual',
        'sync_at' => $now,
        'deleted_at' => $product[4],
        'created_at' => $now,
        'updated_at' => $now,
    ])->all());

    (new CleanupOldSalesJob((string) $tenant->id, [$tenantOldSaleId, $otherOldSaleId], $tenantConnectionName, false))->handle();
    (new CleanupOrphanSalesJob((string) $tenant->id, [$tenantOrphanSaleId, $otherOrphanSaleId], $tenantConnectionName, false))->handle();
    (new DeactivateInactiveProductsJob((string) $tenant->id, [$tenantInactiveProductId, $otherInactiveProductId], $tenantConnectionName, false))->handle();
    (new RestoreSoldProductsJob((string) $tenant->id, [$tenantDeletedProductId, $otherDeletedProductId], $tenantConnectionName, false))->handle();

    expect(DB::connection($tenantConnectionName)->table('sales')->where('id', $tenantOldSaleId)->exists())->toBeFalse()
        ->and(DB::connection($tenantConnectionName)->table('sales')->where('id', $otherOldSaleId)->exists())->toBeTrue()
        ->and(DB::connection($tenantConnectionName)->table('sales')->where('id', $tenantOrphanSaleId)->exists())->toBeFalse()
        ->and(DB::connection($tenantConnectionName)->table('sales')->where('id', $otherOrphanSaleId)->exists())->toBeTrue()
        ->and(DB::connection($tenantConnectionName)->table('products')->where('id', $tenantInactiveProductId)->value('deleted_at'))->not->toBeNull()
        ->and(DB::connection($tenantConnectionName)->table('products')->where('id', $otherInactiveProductId)->value('deleted_at'))->toBeNull()
        ->and(DB::connection($tenantConnectionName)->table('products')->where('id', $tenantDeletedProductId)->value('deleted_at'))->toBeNull()
        ->and(DB::connection($tenantConnectionName)->table('products')->where('id', $otherDeletedProductId)->value('deleted_at'))->not->toBeNull();
});
