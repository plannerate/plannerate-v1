<?php

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\RankedProductDTO;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductSelectionService;
use Callcocam\LaravelRaptorPlannerate\Models\Product;
use Callcocam\LaravelRaptorPlannerate\Services\Analysis\AbcAnalysisService;
use Callcocam\LaravelRaptorPlannerate\Services\Analysis\PaperAnalysisService;
use Callcocam\LaravelRaptorPlannerate\Services\Analysis\TargetStockService;
use Illuminate\Support\Collection;

/*
 * Testes do filtro excludeClassC com presença mínima por subcategoria.
 *
 * Regra corrigida: ao excluir a curva C, nenhuma subcategoria ativa pode sumir
 * do pool — o melhor SKU (maior venda) é mantido. Produtos com retirar_do_mix
 * (recomendação explícita do ABC) saem sempre.
 */

/**
 * Subclasse anônima que expõe o método protected para teste isolado.
 */
function selectionService(): object
{
    return new class(new AbcAnalysisService, new TargetStockService, new PaperAnalysisService) extends ProductSelectionService
    {
        public function exposed(Collection $rankedProducts): Collection
        {
            return $this->excludeClassCWithMinimumPresence($rankedProducts);
        }
    };
}

/**
 * Monta um RankedProductDTO de teste (Product não persistido).
 */
function rankedProduct(
    string $id,
    ?string $abcClass,
    string $subcategoryId,
    float $salesTotal = 100.0,
    bool $retirarDoMix = false,
): RankedProductDTO {
    $product = new Product;
    $product->id = $id;
    $product->name = "Produto {$id}";

    return new RankedProductDTO(
        product: $product,
        abcClass: $abcClass,
        score: match ($abcClass) {
            'A' => 3.0, 'B' => 2.0, 'C' => 1.0, default => 0.0,
        },
        salesTotal: $salesTotal,
        margin: 10.0,
        subcategoryId: $subcategoryId,
        retirarDoMix: $retirarDoMix,
    );
}

it('mantém o top SKU de subcategoria cujo pool inteiro é classe C', function (): void {
    $pool = collect([
        rankedProduct('a1', 'A', 'sub-1', 500.0),
        rankedProduct('c1', 'C', 'sub-2', 80.0),
        rankedProduct('c2', 'C', 'sub-2', 120.0), // maior venda da sub-2 → sobrevive
    ]);

    $result = selectionService()->exposed($pool);
    $ids = $result->map(fn (RankedProductDTO $p) => $p->product->id)->all();

    expect($ids)->toContain('a1')
        ->and($ids)->toContain('c2')
        ->and($ids)->not->toContain('c1')
        ->and($result)->toHaveCount(2);
});

it('remove todos os C de subcategoria que tem produtos A ou B', function (): void {
    $pool = collect([
        rankedProduct('a1', 'A', 'sub-1', 500.0),
        rankedProduct('b1', 'B', 'sub-1', 200.0),
        rankedProduct('c1', 'C', 'sub-1', 50.0),
    ]);

    $result = selectionService()->exposed($pool);
    $ids = $result->map(fn (RankedProductDTO $p) => $p->product->id)->all();

    expect($ids)->toBe(['a1', 'b1']);
});

it('produtos com retirar_do_mix saem sempre, mesmo se a subcategoria zerar', function (): void {
    // sub-2 só tem produtos marcados pelo ABC para sair do mix: a recomendação
    // explícita prevalece sobre a presença mínima
    $pool = collect([
        rankedProduct('a1', 'A', 'sub-1', 500.0),
        rankedProduct('c1', 'C', 'sub-2', 30.0, retirarDoMix: true),
        rankedProduct('c2', 'C', 'sub-2', 10.0, retirarDoMix: true),
    ]);

    $result = selectionService()->exposed($pool);
    $ids = $result->map(fn (RankedProductDTO $p) => $p->product->id)->all();

    expect($ids)->toBe(['a1']);
});

it('produtos sem classificação ABC não são afetados pelo filtro', function (): void {
    $pool = collect([
        rankedProduct('x1', null, 'sub-1', 0.0),
        rankedProduct('c1', 'C', 'sub-2', 40.0),
        rankedProduct('a1', 'A', 'sub-2', 400.0),
    ]);

    $result = selectionService()->exposed($pool);
    $ids = $result->map(fn (RankedProductDTO $p) => $p->product->id)->all();

    expect($ids)->toContain('x1')
        ->and($ids)->toContain('a1')
        ->and($ids)->not->toContain('c1');
});

it('presença mínima ignora C com retirar_do_mix ao escolher o sobrevivente', function (): void {
    // c-mix tem a maior venda da sub-2, mas está marcado para sair do mix —
    // o sobrevivente deve ser o melhor C elegível (c-ok)
    $pool = collect([
        rankedProduct('a1', 'A', 'sub-1', 500.0),
        rankedProduct('c-mix', 'C', 'sub-2', 200.0, retirarDoMix: true),
        rankedProduct('c-ok', 'C', 'sub-2', 90.0),
    ]);

    $result = selectionService()->exposed($pool);
    $ids = $result->map(fn (RankedProductDTO $p) => $p->product->id)->all();

    expect($ids)->toContain('c-ok')
        ->and($ids)->not->toContain('c-mix');
});
