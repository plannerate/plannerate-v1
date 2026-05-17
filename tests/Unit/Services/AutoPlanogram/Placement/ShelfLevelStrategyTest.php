<?php

use App\Enums\ShelfLevel;
use App\Services\AutoPlanogram\DTO\ProductBlock;
use App\Services\AutoPlanogram\DTO\ScoredProduct;
use App\Services\AutoPlanogram\Placement\ShelfLevelStrategy;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// ── Helpers ───────────────────────────────────────────────────────────────────

function strategyProduct(float $score = 0.5, array $metadata = []): ScoredProduct
{
    $product = new Product;
    $product->id = (string) Str::ulid();
    $product->name = 'Test Product';
    $product->ean = '0000000000000';

    return new ScoredProduct(
        productId: $product->id,
        ean: $product->ean,
        score: $score,
        product: $product,
        metadata: $metadata,
    );
}

function strategyBlock(float $aggregateScore, array $children = [], ?string $categoryId = null): ProductBlock
{
    if (empty($children)) {
        $children = [strategyProduct($aggregateScore)];
    }

    return new ProductBlock(
        children: collect($children),
        aggregateScore: $aggregateScore,
        groupingKey: 'test',
        totalWidthEstimate: 30.0,
        adjacencyCategoryId: $categoryId,
    );
}

// ── Testes de fromShelfPosition ────────────────────────────────────────────────

describe('ShelfLevel heuristic logic', function () {
    it('correctly determines shelf level from position in 5-shelf gondola', function () {
        expect(ShelfLevel::fromShelfPosition(0, 5))->toBe(ShelfLevel::High);
        expect(ShelfLevel::fromShelfPosition(1, 5))->toBe(ShelfLevel::Eye);
        expect(ShelfLevel::fromShelfPosition(2, 5))->toBe(ShelfLevel::Eye);
        expect(ShelfLevel::fromShelfPosition(3, 5))->toBe(ShelfLevel::Hand);
        expect(ShelfLevel::fromShelfPosition(4, 5))->toBe(ShelfLevel::Low);
    });

    it('correctly determines shelf level from position in 3-shelf gondola', function () {
        expect(ShelfLevel::fromShelfPosition(0, 3))->toBe(ShelfLevel::High);
        expect(ShelfLevel::fromShelfPosition(1, 3))->toBe(ShelfLevel::Eye);
        expect(ShelfLevel::fromShelfPosition(2, 3))->toBe(ShelfLevel::Low);
    });
});

// ── Testes de ShelfLevelStrategy::decidePreferredLevel ────────────────────────

describe('ShelfLevelStrategy: classificação ABC por aggregateScore', function () {
    beforeEach(function () {
        // Mock DB para evitar acesso ao banco nos testes de unidade
        DB::shouldReceive('connection')->andReturnSelf();
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereNull')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect());
    });

    it('score >= 0.50 → HIGH (top tier)', function () {
        $strategy = new ShelfLevelStrategy('tenant-1');
        $block = strategyBlock(aggregateScore: 0.60);

        expect($strategy->decidePreferredLevel($block))->toBe(ShelfLevel::High);
    });

    it('score exato 0.50 → HIGH (limiar A+)', function () {
        $strategy = new ShelfLevelStrategy('tenant-1');
        $block = strategyBlock(aggregateScore: 0.50);

        expect($strategy->decidePreferredLevel($block))->toBe(ShelfLevel::High);
    });

    it('score >= 0.40 e < 0.50 → EYE (produto A)', function () {
        $strategy = new ShelfLevelStrategy('tenant-1');
        $block = strategyBlock(aggregateScore: 0.45);

        expect($strategy->decidePreferredLevel($block))->toBe(ShelfLevel::Eye);
    });

    it('score exato 0.40 → EYE (limiar A)', function () {
        $strategy = new ShelfLevelStrategy('tenant-1');
        $block = strategyBlock(aggregateScore: 0.40);

        expect($strategy->decidePreferredLevel($block))->toBe(ShelfLevel::Eye);
    });

    it('score >= 0.35 e < 0.40 → HAND (produto B)', function () {
        $strategy = new ShelfLevelStrategy('tenant-1');
        $block = strategyBlock(aggregateScore: 0.37);

        expect($strategy->decidePreferredLevel($block))->toBe(ShelfLevel::Hand);
    });

    it('score exato 0.35 → HAND (limiar B)', function () {
        $strategy = new ShelfLevelStrategy('tenant-1');
        $block = strategyBlock(aggregateScore: 0.35);

        expect($strategy->decidePreferredLevel($block))->toBe(ShelfLevel::Hand);
    });

    it('score < 0.35 → LOW (produto C)', function () {
        $strategy = new ShelfLevelStrategy('tenant-1');
        $block = strategyBlock(aggregateScore: 0.20);

        expect($strategy->decidePreferredLevel($block))->toBe(ShelfLevel::Low);
    });

    it('produto estratégico (strategic >= 1.0) → HIGH independente do score', function () {
        $strategy = new ShelfLevelStrategy('tenant-1');
        $child = strategyProduct(score: 0.10, metadata: ['strategic' => 1.0]);
        $block = strategyBlock(aggregateScore: 0.10, children: [$child]);

        expect($strategy->decidePreferredLevel($block))->toBe(ShelfLevel::High);
    });

});

describe('ShelfLevelStrategy: preferência explícita por categoria', function () {
    it('sobrepõe o score ABC quando categoria tem preferência configurada', function () {
        $categoryId = 'cat-123';
        $fakeRow = (object) ['category_id' => $categoryId, 'preferred_level' => 'eye'];

        DB::shouldReceive('connection')->andReturnSelf();
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereNull')->andReturnSelf();
        DB::shouldReceive('get')->once()->andReturn(collect([$fakeRow]));

        $strategy = new ShelfLevelStrategy('tenant-1');
        // Score baixo → seria LOW, mas preferência explícita da categoria diz EYE
        $block = strategyBlock(aggregateScore: 0.10, categoryId: $categoryId);

        expect($strategy->decidePreferredLevel($block))->toBe(ShelfLevel::Eye);
    });
});
