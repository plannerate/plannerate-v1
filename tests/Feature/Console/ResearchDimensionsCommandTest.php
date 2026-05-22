<?php

use App\Enums\DimensionStatus;
use App\Jobs\ResearchProductDimensionsJob;
use App\Models\Product;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

test('command enfileira jobs para produtos com status pending e ean', function (): void {
    Queue::fake();

    $product = new Product;
    $product->id = (string) Str::ulid();
    $product->name = 'Produto Pendente';
    $product->ean = '7890000000010';
    $product->status = 'published';
    $product->dimension_status = DimensionStatus::Pending;
    Product::withoutEvents(fn () => $product->save());

    $productSemEan = new Product;
    $productSemEan->id = (string) Str::ulid();
    $productSemEan->name = 'Produto Sem EAN';
    $productSemEan->ean = null;
    $productSemEan->status = 'published';
    $productSemEan->dimension_status = DimensionStatus::Pending;
    Product::withoutEvents(fn () => $productSemEan->save());

    $this->artisan('products:research-dimensions', ['--status' => 'pending'])
        ->assertSuccessful()
        ->expectsOutputToContain('1 produto(s) enfileirado(s)');

    Queue::assertPushed(ResearchProductDimensionsJob::class, 1);
});

test('command respeita o limite de produtos', function (): void {
    Queue::fake();

    foreach (range(1, 5) as $i) {
        $p = new Product;
        $p->id = (string) Str::ulid();
        $p->name = "Produto {$i}";
        $p->ean = "789000000010{$i}";
        $p->status = 'published';
        $p->dimension_status = DimensionStatus::Pending;
        Product::withoutEvents(fn () => $p->save());
    }

    $this->artisan('products:research-dimensions', ['--status' => 'pending', '--limit' => 3])
        ->assertSuccessful();

    Queue::assertPushed(ResearchProductDimensionsJob::class, 3);
});

test('command funciona com status rejected', function (): void {
    Queue::fake();

    $product = new Product;
    $product->id = (string) Str::ulid();
    $product->name = 'Produto Rejeitado';
    $product->ean = '7890000000020';
    $product->status = 'published';
    $product->dimension_status = DimensionStatus::Rejected;
    Product::withoutEvents(fn () => $product->save());

    $this->artisan('products:research-dimensions', ['--status' => 'rejected'])
        ->assertSuccessful();

    Queue::assertPushed(ResearchProductDimensionsJob::class, 1);
});

test('command falha com status inválido', function (): void {
    $this->artisan('products:research-dimensions', ['--status' => 'approved'])
        ->assertFailed()
        ->expectsOutputToContain('Status inválido');
});

test('command informa quando não há produtos', function (): void {
    Queue::fake();

    $this->artisan('products:research-dimensions', ['--status' => 'pending'])
        ->assertSuccessful()
        ->expectsOutputToContain('Nenhum produto');

    Queue::assertNothingPushed();
});
