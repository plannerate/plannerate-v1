<?php

use App\Enums\DimensionStatus;
use App\Jobs\ResearchProductDimensionsJob;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductDimensionService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    // Purge cached :memory: connections so each test gets a fresh database
    DB::purge('tenant');
    DB::purge('landlord');

    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => 'database/migrations',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    Artisan::call('migrate', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

test('research define status pending e enfileira job', function (): void {
    Queue::fake();

    $product = new Product;
    $product->id = (string) Str::ulid();
    $product->tenant_id = (string) Str::ulid();
    $product->name = 'Produto Teste';
    $product->ean = '7890000000099';
    $product->status = 'published';
    $product->dimension_status = DimensionStatus::Rejected;
    Product::withoutEvents(fn () => $product->save());

    $service = new ProductDimensionService;
    $service->research($product);

    Queue::assertPushed(
        ResearchProductDimensionsJob::class,
        fn ($job) => $job->productId === $product->id,
    );

    $product->refresh();
    expect($product->dimension_status)->toBe(DimensionStatus::Pending);
});

test('approve define status approved e registra aprovador', function (): void {
    $product = new Product;
    $product->id = (string) Str::ulid();
    $product->name = 'Produto Aprovação';
    $product->ean = '7890000000098';
    $product->status = 'published';
    $product->dimension_status = DimensionStatus::AwaitingApproval;
    Product::withoutEvents(fn () => $product->save());

    $user = new User;
    $user->id = (string) Str::ulid();

    $service = new ProductDimensionService;
    $service->approve($product, $user);

    $product->refresh();

    expect($product->dimension_status)->toBe(DimensionStatus::Approved)
        ->and($product->dimension_approved_by)->toBe($user->id)
        ->and($product->dimension_approved_at)->not->toBeNull();
});

test('reject define status rejected e adiciona warning com motivo', function (): void {
    $product = new Product;
    $product->id = (string) Str::ulid();
    $product->name = 'Produto Rejeição';
    $product->ean = '7890000000097';
    $product->status = 'published';
    $product->dimension_status = DimensionStatus::AwaitingApproval;
    $product->dimension_warnings = [];
    Product::withoutEvents(fn () => $product->save());

    $user = new User;
    $user->id = (string) Str::ulid();
    $user->name = 'João Revisor';

    $service = new ProductDimensionService;
    $service->reject($product, $user, 'Dimensões muito diferentes do físico.');

    $product->refresh();

    expect($product->dimension_status)->toBe(DimensionStatus::Rejected)
        ->and($product->dimension_warnings)->toHaveCount(1)
        ->and($product->dimension_warnings[0])->toContain('Dimensões muito diferentes do físico.');
});

test('dispatchPendingBatch enfileira apenas produtos com tenant_id e status pending', function (): void {
    Queue::fake();

    $tenantId = (string) Str::ulid();

    $withTenant = new Product;
    $withTenant->id = (string) Str::ulid();
    $withTenant->tenant_id = $tenantId;
    $withTenant->name = 'Com Tenant';
    $withTenant->ean = '7890000000096';
    $withTenant->status = 'published';
    $withTenant->dimension_status = DimensionStatus::Pending;
    Product::withoutEvents(fn () => $withTenant->save());

    $withoutTenant = new Product;
    $withoutTenant->id = (string) Str::ulid();
    $withoutTenant->tenant_id = null;
    $withoutTenant->name = 'Sem Tenant';
    $withoutTenant->ean = '7890000000095';
    $withoutTenant->status = 'published';
    $withoutTenant->dimension_status = DimensionStatus::Pending;
    Product::withoutEvents(fn () => $withoutTenant->save());

    $service = new ProductDimensionService;
    $dispatched = $service->dispatchPendingBatch(10);

    expect($dispatched)->toBe(1);
    Queue::assertPushed(ResearchProductDimensionsJob::class, 1);
});
