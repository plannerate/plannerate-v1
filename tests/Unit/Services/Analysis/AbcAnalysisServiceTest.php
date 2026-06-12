<?php

use Callcocam\LaravelRaptorPlannerate\Services\Analysis\AbcAnalysisService;
use Illuminate\Support\Collection;

/*
 * Testes da classificação ABC pura (classifyRankedProducts).
 *
 * Regra corrigida: a classe é definida pelo percentual acumulado ANTES de somar
 * o item — o primeiro do ranking é sempre A (inclusive em categorias com um
 * único produto) e um item que cruza um corte ainda pertence à classe anterior.
 */

/**
 * Monta a coleção de entrada no formato esperado pelo service
 * (ordenada desc por media_ponderada, como faz o chamador real).
 *
 * @param  array<string, float>  $weights  mapa product_id => media_ponderada
 */
function abcInput(array $weights): Collection
{
    return collect($weights)
        ->map(fn (float $weight, string $id) => ['product_id' => $id, 'media_ponderada' => $weight])
        ->values()
        ->sortByDesc('media_ponderada')
        ->values();
}

beforeEach(function (): void {
    $this->service = (new AbcAnalysisService)->setCuts(0.80, 0.90);
});

it('categoria com 1 produto classifica o único produto como A', function (): void {
    $input = abcInput(['p1' => 42.0]);

    $result = $this->service->classifyRankedProducts($input, 42.0);

    expect($result)->toHaveCount(1)
        ->and($result->first()['classificacao'])->toBe('A')
        ->and($result->first()['ranking'])->toBe(1)
        ->and($result->first()['retirar_do_mix'])->toBeFalse();
});

it('primeiro do ranking é sempre A mesmo com participação individual acima do corte B', function (): void {
    // p1 concentra 95% da categoria — antes da correção viraria C (acumulado 95% > 90%)
    $input = abcInput(['p1' => 95.0, 'p2' => 5.0]);

    $result = $this->service->classifyRankedProducts($input, 100.0);

    expect($result[0]['classificacao'])->toBe('A')
        ->and($result[1]['classificacao'])->toBe('C');
});

it('produto que cruza o corte A ainda é A (classificação pelo acumulado antes do item)', function (): void {
    // p2 começa em 50% e termina em 90%: cruza o corte A (80%) mas pertence à classe A
    $input = abcInput(['p1' => 50.0, 'p2' => 40.0, 'p3' => 10.0]);

    $result = $this->service->classifyRankedProducts($input, 100.0);

    expect($result[0]['classificacao'])->toBe('A')
        ->and($result[1]['classificacao'])->toBe('A')
        ->and($result[2]['classificacao'])->toBe('C'); // começa exatamente no corte B (90%)
});

it('distribui A/B/C respeitando cortes 0.80/0.90 com 10 produtos de participação decrescente', function (): void {
    $input = abcInput([
        'p01' => 30.0, 'p02' => 20.0, 'p03' => 15.0, 'p04' => 10.0, 'p05' => 8.0,
        'p06' => 6.0, 'p07' => 4.0, 'p08' => 3.0, 'p09' => 2.0, 'p10' => 2.0,
    ]);

    $result = $this->service->classifyRankedProducts($input, 100.0);

    $classes = $result->pluck('classificacao')->all();

    // Acumulado antes: 0, 30, 50, 65, 75 (A) | 83, 89 (B) | 93, 96, 98 (C)
    expect($classes)->toBe(['A', 'A', 'A', 'A', 'A', 'B', 'B', 'C', 'C', 'C'])
        ->and($result->pluck('ranking')->all())->toBe(range(1, 10));
});

it('categoria inteira sem vendas mantém todos como C e nenhum retirar_do_mix', function (): void {
    $input = abcInput(['p1' => 0.0, 'p2' => 0.0, 'p3' => 0.0]);

    $result = $this->service->classifyRankedProducts($input, 0.0);

    expect($result->pluck('classificacao')->unique()->all())->toBe(['C'])
        ->and($result->pluck('retirar_do_mix')->filter()->all())->toBe([])
        ->and($result->pluck('ranking')->all())->toBe([1, 2, 3]);
});

it('retirar_do_mix marca apenas C com percentual menor que metade do menor B', function (): void {
    // Acumulado antes: 0, 40, 70 (A) | 85 (B, 8%) | 93 (C, 6%), 99 (C, 1%)
    // Menor B = 8% → metade = 4%: C de 6% permanece, C de 1% sai do mix
    $input = abcInput([
        'p1' => 40.0, 'p2' => 30.0, 'p3' => 15.0,
        'p4' => 8.0, 'p5' => 6.0, 'p6' => 1.0,
    ]);

    $result = $this->service->classifyRankedProducts($input, 100.0);

    expect($result[3]['classificacao'])->toBe('B')
        ->and($result[4]['classificacao'])->toBe('C')
        ->and($result[4]['retirar_do_mix'])->toBeFalse()
        ->and($result[5]['classificacao'])->toBe('C')
        ->and($result[5]['retirar_do_mix'])->toBeTrue();
});

it('categoria sem classe B não retira ninguém do mix', function (): void {
    // Acumulado antes: 0 (A) | 95, 98 (C) — nenhum B → sem referência para retirar
    $input = abcInput(['p1' => 95.0, 'p2' => 3.0, 'p3' => 2.0]);

    $result = $this->service->classifyRankedProducts($input, 100.0);

    expect($result->pluck('classificacao')->all())->toBe(['A', 'C', 'C'])
        ->and($result->pluck('retirar_do_mix')->filter()->all())->toBe([]);
});

it('percentual_acumulado retornado é o acumulado após o item (exibição)', function (): void {
    $input = abcInput(['p1' => 60.0, 'p2' => 40.0]);

    $result = $this->service->classifyRankedProducts($input, 100.0);

    expect($result[0]['percentual_acumulado'])->toEqualWithDelta(0.6, 0.0001)
        ->and($result[1]['percentual_acumulado'])->toEqualWithDelta(1.0, 0.0001);
});
