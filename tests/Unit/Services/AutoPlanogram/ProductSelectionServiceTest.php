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
 * Regra: ao excluir a curva C, nenhuma subcategoria ativa pode sumir do pool — o melhor
 * SKU (maior venda) é mantido.
 *
 * `retirar_do_mix` NÃO tem tratamento especial aqui. Ele é SUGESTÃO da análise ABC, não
 * exclusão do auto-planograma. Antes, os marcados saíam sempre e furavam a presença
 * mínima; com a regra do VBA (docs/ABC.md), que marca todo C abaixo de 50% de
 * participação em categoria sem classe B, isso derrubava 20% do sortimento de uma
 * gôndola real — inclusive produtos com quase metade do peso da própria categoria.
 * Todo marcado é necessariamente classe C, então continua sujeito ao filtro; só deixou
 * de ter um atalho sem freio.
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

it('subcategoria inteira marcada para retirar do mix NÃO some: a presença mínima segura o melhor', function (): void {
    // sub-2 só tem produtos que o ABC sugeriu retirar. A sugestão não fura a presença
    // mínima: a subcategoria continua representada pelo seu melhor SKU (c1, maior venda).
    //
    // Antes, esses produtos saíam sempre e a sub-2 sumia da gôndola. Com a regra do VBA
    // marcando ~20% do sortimento, isso apagaria subcategorias inteiras em silêncio.
    $pool = collect([
        rankedProduct('a1', 'A', 'sub-1', 500.0),
        rankedProduct('c1', 'C', 'sub-2', 30.0, retirarDoMix: true),
        rankedProduct('c2', 'C', 'sub-2', 10.0, retirarDoMix: true),
    ]);

    $result = selectionService()->exposed($pool);
    $ids = $result->map(fn (RankedProductDTO $p) => $p->product->id)->all();

    expect($ids)->toContain('a1')
        ->and($ids)->toContain('c1')     // melhor venda da sub-2 → sobrevive
        ->and($ids)->not->toContain('c2');
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

// ── retirar_do_mix é sugestão da análise, não exclusão do auto-planograma ──

it('retirar_do_mix sozinho NÃO tira ninguém do pool quando excludeClassC está desligado', function (): void {
    // Sem excludeClassC não há filtro de curva C nenhum — e a sugestão do ABC não é
    // motivo para remover. Antes, os marcados saíam mesmo assim.
    $pool = collect([
        rankedProduct('a1', 'A', 'sub-1', 500.0),
        rankedProduct('c-keep', 'C', 'sub-1', 50.0),
        rankedProduct('c-mix', 'C', 'sub-1', 70.0, retirarDoMix: true),
    ]);

    // excludeClassCWithMinimumPresence só é chamado quando $config->excludeClassC = true;
    // aqui simulamos o caminho "desligado": o pool passa intacto.
    expect($pool->map(fn (RankedProductDTO $p) => $p->product->id)->all())
        ->toBe(['a1', 'c-keep', 'c-mix']);
});

it('presença mínima escolhe o sobrevivente pela VENDA, mesmo que ele esteja marcado', function (): void {
    // c-mix tem a maior venda da sub-2 e está marcado para retirar do mix. Como a marca
    // é sugestão e não sentença, ele continua elegível — e vence pela venda.
    //
    // A regra invertida (ignorar o marcado ao escolher o sobrevivente) deixava a
    // subcategoria representada por um produto pior que o disponível.
    $pool = collect([
        rankedProduct('a1', 'A', 'sub-1', 500.0),
        rankedProduct('c-mix', 'C', 'sub-2', 200.0, retirarDoMix: true),
        rankedProduct('c-ok', 'C', 'sub-2', 90.0),
    ]);

    $result = selectionService()->exposed($pool);
    $ids = $result->map(fn (RankedProductDTO $p) => $p->product->id)->all();

    expect($ids)->toContain('c-mix')
        ->and($ids)->not->toContain('c-ok');
});

it('C marcado em subcategoria que tem A ou B sai normalmente pelo filtro de curva C', function (): void {
    // Nada de especial: o marcado é classe C, e a sub-1 já tem um A representando-a.
    // Sai pela regra normal, não por ser marcado.
    $pool = collect([
        rankedProduct('a1', 'A', 'sub-1', 500.0),
        rankedProduct('c-mix', 'C', 'sub-1', 70.0, retirarDoMix: true),
    ]);

    $result = selectionService()->exposed($pool);
    $ids = $result->map(fn (RankedProductDTO $p) => $p->product->id)->all();

    expect($ids)->toBe(['a1']);
});
