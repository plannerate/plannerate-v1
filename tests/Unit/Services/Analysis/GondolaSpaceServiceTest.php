<?php

use Callcocam\LaravelRaptorPlannerate\Services\Analysis\GondolaSpaceService;
use Illuminate\Support\Collection;

/*
 * Testes da etapa pura do cálculo de espaço de gôndola (aggregate).
 *
 * espaco_linear_cm = Σ (layer.quantity × product.width) — mesma fórmula do editor.
 * share_gondola    = espaço do produto ÷ espaço total ocupado × 100.
 */

/**
 * Monta a coleção de layers no formato produzido pela query de spaceByProduct.
 *
 * @param  array<int, array{0: string, 1: int|null, 2: float|null}>  $rows  [product_id, quantity, width]
 */
function spaceInput(array $rows): Collection
{
    return collect($rows)->map(fn (array $row) => (object) [
        'product_id' => $row[0],
        'quantity' => $row[1],
        'width' => $row[2],
    ]);
}

beforeEach(function (): void {
    $this->service = new GondolaSpaceService;
});

it('soma frentes e espaço linear, e o share fecha em 100%', function (): void {
    // a: 3 frentes × 10cm = 30cm | b: 1 frente × 10cm = 10cm → total 40cm
    $result = $this->service->aggregate(spaceInput([
        ['a', 3, 10.0],
        ['b', 1, 10.0],
    ]));

    expect($result['a']['facings'])->toBe(3)
        ->and($result['a']['espaco_linear_cm'])->toBe(30.0)
        ->and($result['a']['share_gondola'])->toBe(75.0)
        ->and($result['b']['share_gondola'])->toBe(25.0)
        ->and(array_sum(array_column($result, 'share_gondola')))->toEqualWithDelta(100.0, 0.001);
});

it('soma o mesmo produto exposto em vários layers da gôndola', function (): void {
    // Produto repetido em prateleiras/segmentos diferentes: tudo soma.
    $result = $this->service->aggregate(spaceInput([
        ['repetido', 2, 5.0],   // 10cm
        ['repetido', 4, 5.0],   // 20cm
        ['outro', 1, 10.0],     // 10cm
    ]));

    expect($result['repetido']['facings'])->toBe(6)
        ->and($result['repetido']['espaco_linear_cm'])->toBe(30.0)
        ->and($result['repetido']['share_gondola'])->toBe(75.0);
});

it('produto largo com poucas frentes pode ocupar mais que um estreito com muitas', function (): void {
    // É a razão de o share ser LINEAR e não por contagem de frentes:
    // 2 frentes de 30cm (60cm) ocupam mais gôndola que 5 frentes de 5cm (25cm).
    $result = $this->service->aggregate(spaceInput([
        ['largo', 2, 30.0],
        ['estreito', 5, 5.0],
    ]));

    expect($result['estreito']['facings'])->toBeGreaterThan($result['largo']['facings'])
        ->and($result['largo']['espaco_linear_cm'])->toBeGreaterThan($result['estreito']['espaco_linear_cm'])
        ->and($result['largo']['share_gondola'])->toBeGreaterThan($result['estreito']['share_gondola']);
});

it('quantidade nula ou zerada conta como uma frente (igual ao editor)', function (): void {
    $result = $this->service->aggregate(spaceInput([
        ['sem-qtde', null, 10.0],
        ['qtde-zero', 0, 10.0],
    ]));

    expect($result['sem-qtde']['facings'])->toBe(1)
        ->and($result['qtde-zero']['facings'])->toBe(1)
        ->and($result['qtde-zero']['espaco_linear_cm'])->toBe(10.0);
});

it('produto sem largura cadastrada é sinalizado em vez de parecer que não ocupa nada', function (): void {
    // Sem o flag, este produto teria share 0 e a análise recomendaria "aumentar
    // frentes" para um item que talvez já ocupe meia gôndola — o dado é que falta.
    $result = $this->service->aggregate(spaceInput([
        ['com-dimensao', 2, 10.0],
        ['sem-dimensao', 5, null],
    ]));

    expect($result['sem-dimensao']['sem_dimensao'])->toBeTrue()
        ->and($result['sem-dimensao']['facings'])->toBe(5)
        ->and($result['sem-dimensao']['espaco_linear_cm'])->toBe(0.0)
        ->and($result['sem-dimensao']['share_gondola'])->toBe(0.0)
        ->and($result['com-dimensao']['sem_dimensao'])->toBeFalse()
        // o produto com dimensão fica com 100% do espaço MENSURÁVEL
        ->and($result['com-dimensao']['share_gondola'])->toBe(100.0);
});

it('gôndola sem nenhuma dimensão cadastrada não divide por zero', function (): void {
    $result = $this->service->aggregate(spaceInput([
        ['a', 2, 0.0],
        ['b', 3, null],
    ]));

    expect($result['a']['share_gondola'])->toBe(0.0)
        ->and($result['b']['share_gondola'])->toBe(0.0)
        ->and($result['a']['sem_dimensao'])->toBeTrue();
});

it('gôndola vazia devolve array vazio', function (): void {
    expect($this->service->aggregate(collect()))->toBe([]);
});
