<?php

use App\Enums\DimensionStatus;
use App\Jobs\ResearchProductDimensionsJob;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductDimensionService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    // Migrate tenant connection (separate from the default RefreshDatabase transaction)
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
    $product->name = 'Produto Teste';
    $product->ean = '7890000000099';
    $product->status = 'published';
    $product->dimension_status = DimensionStatus::Rejected;
    Product::withoutEvents(fn () => $product->save());

    $service = new ProductDimensionService;
    $service->research($product);

    Queue::assertPushed(ResearchProductDimensionsJob::class, fn ($job) => $job->product->id === $product->id);

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

    // Usa User sem persistir no banco para evitar complexidade do multi-tenant
    $user = new User;
    $user->id = (string) Str::ulid();

    $service = new ProductDimensionService;
    $service->approve($product, $user);

    $product->refresh();

    expect($product->dimension_status)->toBe(DimensionStatus::Approved)
        ->and($product->dimension_approved_by)->toBe($user->id)
        ->and($product->dimension_approved_at)->not->toBeNull();
});

test('reject define status rejected e adiciona warning', function (): void {
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

    $service = new ProductDimensionService;
    $service->reject($product, $user, 'Dimensões muito diferentes do físico.');

    $product->refresh();

    expect($product->dimension_status)->toBe(DimensionStatus::Rejected)
        ->and($product->dimension_warnings)->toContain('Dimensões muito diferentes do físico.');
});

test('dispatchPendingBatch enfileira apenas produtos com ean e status pending', function (): void {
    Queue::fake();

    $withEan = new Product;
    $withEan->id = (string) Str::ulid();
    $withEan->name = 'Com EAN';
    $withEan->ean = '7890000000096';
    $withEan->status = 'published';
    $withEan->dimension_status = DimensionStatus::Pending;
    Product::withoutEvents(fn () => $withEan->save());

    $withoutEan = new Product;
    $withoutEan->id = (string) Str::ulid();
    $withoutEan->name = 'Sem EAN';
    $withoutEan->ean = null;
    $withoutEan->status = 'published';
    $withoutEan->dimension_status = DimensionStatus::Pending;
    Product::withoutEvents(fn () => $withoutEan->save());

    $service = new ProductDimensionService;
    $dispatched = $service->dispatchPendingBatch(10);

    expect($dispatched)->toBe(1);
    Queue::assertPushed(ResearchProductDimensionsJob::class, 1);
});
