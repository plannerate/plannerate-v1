<?php

use Callcocam\LaravelRaptorPlannerate\Services\Analysis\AbcAnalysisService;
use Illuminate\Support\Collection;

/*
 * Testes da classificação ABC pura (classifyRankedProducts).
 *
 * Regra (restaurada em 13/07/2026, ver AbcSpreadsheetParityTest):
 *   - a classe sai do percentual acumulado APÓS somar o item;
 *   - os cortes são inclusivos (<=);
 *   - exceção única: o 1º do ranking é sempre A (o líder do grupo nunca é o pior dele).
 *
 * Entre 12/06 e 13/07/2026 o serviço classificava pelo acumulado ANTES do item
 * (commit 01da121b). Isso promovia TODO item que cruzasse um corte, dava um A a mais
 * em cada grupo (62,6% de classe A numa gôndola real) e divergia da planilha de
 * referência do cliente. Os testes abaixo travam a regra correta.
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

it('categoria com 1 produto classifica o único produto como A (exceção do rank 1)', function (): void {
    // Acumulado 100% — pela regra pura cairia em C. É o caso degenerado que a exceção
    // do líder resolve: o único produto da categoria não pode ser o pior dela.
    $input = abcInput(['p1' => 42.0]);

    $result = $this->service->classifyRankedProducts($input, 42.0);

    expect($result)->toHaveCount(1)
        ->and($result->first()['classificacao'])->toBe('A')
        ->and($result->first()['ranking'])->toBe(1)
        ->and($result->first()['retirar_do_mix'])->toBeFalse();
});

it('primeiro do ranking é sempre A mesmo dominando a categoria inteira', function (): void {
    // p1 sozinho é 95% (acumulado 95% > corte B de 90%): pela regra pura seria C.
    $input = abcInput(['p1' => 95.0, 'p2' => 5.0]);

    $result = $this->service->classifyRankedProducts($input, 100.0);

    expect($result[0]['classificacao'])->toBe('A')
        ->and($result[1]['classificacao'])->toBe('C'); // acumulado 100%
});

it('produto que cruza o corte A cai na classe seguinte (acumulado APÓS o item)', function (): void {
    // p2 fecha em 90% de acumulado: cruzou o corte A (80%), então NÃO é A.
    // 90% <= corte B (90%), e o corte é inclusivo → B.
    // É exatamente aqui que a regra de 12/06 divergia: ela devolvia A.
    $input = abcInput(['p1' => 50.0, 'p2' => 40.0, 'p3' => 10.0]);

    $result = $this->service->classifyRankedProducts($input, 100.0);

    expect($result[0]['classificacao'])->toBe('A')  // rank 1
        ->and($result[1]['classificacao'])->toBe('B')  // acumulado 90%
        ->and($result[2]['classificacao'])->toBe('C'); // acumulado 100%
});

it('distribui A/B/C respeitando cortes 0.80/0.90 com 10 produtos de participação decrescente', function (): void {
    $input = abcInput([
        'p01' => 30.0, 'p02' => 20.0, 'p03' => 15.0, 'p04' => 10.0, 'p05' => 8.0,
        'p06' => 6.0, 'p07' => 4.0, 'p08' => 3.0, 'p09' => 2.0, 'p10' => 2.0,
    ]);

    $result = $this->service->classifyRankedProducts($input, 100.0);

    $classes = $result->pluck('classificacao')->all();

    // Acumulado APÓS: 30, 50, 65, 75 (<=80 → A) | 83, 89 (<=90 → B) | 93, 96, 98, 100 (C)
    expect($classes)->toBe(['A', 'A', 'A', 'A', 'B', 'B', 'C', 'C', 'C', 'C'])
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
    // Participações: 40, 30, 15, 8, 6, 1 (%)
    // Acumulado APÓS:  40(A) 70(A) 85(B) 93(C) 99(C) 100(C)
    // Menor B = 15% → metade = 7,5%. Dos C: 8% fica, 6% e 1% saem do mix.
    $input = abcInput([
        'p1' => 40.0, 'p2' => 30.0, 'p3' => 15.0,
        'p4' => 8.0, 'p5' => 6.0, 'p6' => 1.0,
    ]);

    $result = $this->service->classifyRankedProducts($input, 100.0);

    expect($result->pluck('classificacao')->all())->toBe(['A', 'A', 'B', 'C', 'C', 'C'])
        ->and($result[3]['retirar_do_mix'])->toBeFalse()  // C de 8% > metade do menor B
        ->and($result[4]['retirar_do_mix'])->toBeTrue()   // C de 6% < 7,5%
        ->and($result[5]['retirar_do_mix'])->toBeTrue();  // C de 1% < 7,5%
});

it('categoria sem classe B não retira ninguém do mix', function (): void {
    // Acumulado APÓS: 95 (rank 1 → A pela exceção) | 98, 100 (C).
    // Nenhum B → não há referência de corte, ninguém sai do mix.
    $input = abcInput(['p1' => 95.0, 'p2' => 3.0, 'p3' => 2.0]);

    $result = $this->service->classifyRankedProducts($input, 100.0);

    expect($result->pluck('classificacao')->all())->toBe(['A', 'C', 'C'])
        ->and($result->pluck('retirar_do_mix')->filter()->all())->toBe([]);
});

it('o acumulado retornado é o mesmo que decidiu a classe', function (): void {
    // Antes da correção o serviço classificava por um número e devolvia outro para
    // exibição — a linha na tela se contradizia (acumulado 93% ao lado de classe A).
    $input = abcInput(['p1' => 60.0, 'p2' => 40.0]);

    $result = $this->service->classifyRankedProducts($input, 100.0);

    expect($result[0]['percentual_acumulado'])->toEqualWithDelta(0.6, 0.0001)
        ->and($result[0]['classificacao'])->toBe('A')   // 60% <= 80%
        ->and($result[1]['percentual_acumulado'])->toEqualWithDelta(1.0, 0.0001)
        ->and($result[1]['classificacao'])->toBe('C');  // 100% > 90%
});
