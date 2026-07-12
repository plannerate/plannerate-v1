<?php

/**
 * Testes do empacotador exato de prateleira (Fase 2 — docs/gondola-precisao-automatica/).
 *
 * O que precisa ficar provado aqui:
 *  1. o empacotador NUNCA promete um encaixe que a prateleira não comporta;
 *  2. ele NUNCA perde um produto que o motor guloso colocaria (não-regressão);
 *  3. ele acha combinações de frentes que o round-robin guloso não acha (o ganho de precisão).
 */

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PackCandidate;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement\ShelfKnapsackPacker;

/**
 * Candidato com valores neutros — cada teste sobrescreve só o que lhe interessa.
 */
function packCandidate(
    float $width,
    int $min = 1,
    int $max = 1,
    bool $forced = false,
    float $inclusion = 0.75,
    float $facingWeight = 1.0,
): PackCandidate {
    return new PackCandidate(
        singleWidth: $width,
        minFacings: $min,
        maxFacings: $max,
        inclusionScore: $inclusion,
        facingWeight: $facingWeight,
        forced: $forced,
    );
}

/**
 * Largura total consumida por uma solução, já com a folga entre produtos vizinhos.
 *
 * @param  list<PackCandidate>  $candidates
 * @param  array<int, int>  $solution
 */
function packedWidth(array $candidates, array $solution, float $spacing = 0.0): float
{
    $width = 0.0;
    $placed = 0;

    foreach ($solution as $index => $facings) {
        if ($facings <= 0) {
            continue;
        }

        $width += $candidates[$index]->singleWidth * $facings;
        $placed++;
    }

    return $width + max(0, $placed - 1) * $spacing;
}

// ── invariantes de segurança ──────────────────────────────────────────────────

test('nunca estoura a largura disponível', function (): void {
    $candidates = [
        packCandidate(13.0, max: 5),
        packCandidate(7.5, max: 5),
        packCandidate(21.3, max: 5),
        packCandidate(4.2, max: 5),
    ];

    $solution = (new ShelfKnapsackPacker)->pack($candidates, 100.0);

    expect($solution)->not->toBeNull()
        ->and(packedWidth($candidates, $solution))->toBeLessThanOrEqual(100.0);
});

test('produto obrigatório entra sempre, mesmo disputando com opcionais mais estreitos', function (): void {
    // O obrigatório é largo e "ineficiente": sem a trava, o DP preferiria dois estreitos.
    $candidates = [
        packCandidate(40.0, forced: true, inclusion: 0.6),
        packCandidate(15.0, inclusion: 1.0),
        packCandidate(15.0, inclusion: 1.0),
    ];

    $solution = (new ShelfKnapsackPacker)->pack($candidates, 50.0);

    expect($solution[0])->toBeGreaterThanOrEqual(1);
});

test('obrigatório que não cabe aborta o empacotador (o guloso segue valendo)', function (): void {
    $solution = (new ShelfKnapsackPacker)->pack([packCandidate(80.0, forced: true)], 50.0);

    expect($solution)->toBeNull();
});

test('acima do teto de candidatos devolve null em vez de travar a geração', function (): void {
    $candidates = array_fill(0, ShelfKnapsackPacker::MAX_CANDIDATES + 1, packCandidate(5.0));

    expect((new ShelfKnapsackPacker)->pack($candidates, 500.0))->toBeNull();
});

// ── limites do slot ───────────────────────────────────────────────────────────

test('respeita o teto de frentes mesmo com espaço sobrando', function (): void {
    // 1 produto de 10cm com teto de 3 frentes numa prateleira de 100cm:
    // 30cm ocupados e 70cm de sobra é o resultado CORRETO — o teto é regra de negócio.
    $candidates = [packCandidate(10.0, min: 1, max: 3)];

    $solution = (new ShelfKnapsackPacker)->pack($candidates, 100.0);

    expect($solution[0])->toBe(3);
});

test('respeita a frente mínima: entra com o mínimo ou não entra', function (): void {
    // Mínimo de 3 frentes × 12cm = 36cm; só há 30cm → não entra (não existe "meio produto").
    $candidates = [packCandidate(12.0, min: 3, max: 5)];

    $solution = (new ShelfKnapsackPacker)->pack($candidates, 30.0);

    expect($solution[0])->toBe(0);
});

// ── folga entre produtos ──────────────────────────────────────────────────────

test('a folga é cobrada entre produtos, não no fim da prateleira', function (): void {
    // 3 produtos de 10cm + 2 folgas de 2cm = 34cm. Numa prateleira de 34cm cabem os três:
    // se a folga fosse cobrada também depois do último (3 folgas = 36cm), um deles ficaria de fora.
    $candidates = [
        packCandidate(10.0),
        packCandidate(10.0),
        packCandidate(10.0),
    ];

    $solution = (new ShelfKnapsackPacker)->pack($candidates, 34.0, spacing: 2.0);

    expect(array_sum($solution))->toBe(3)
        ->and(packedWidth($candidates, $solution, 2.0))->toBeLessThanOrEqual(34.0);
});

// ── prioridades do negócio ────────────────────────────────────────────────────

test('variedade antes de profundidade: prefere incluir um SKU a engordar as frentes de outro', function (): void {
    // Cabem 20cm além do primeiro produto: dá para dar +2 frentes ao produto A (10cm cada)
    // OU incluir o produto B. Incluir B tem que vencer — é o mesmo princípio que o overflow
    // pass já aplica ("variedade < profundidade — errado").
    $candidates = [
        packCandidate(10.0, min: 1, max: 5, forced: true, inclusion: 1.0, facingWeight: 1.0),
        packCandidate(20.0, min: 1, max: 1, inclusion: 0.5, facingWeight: 0.05),
    ];

    $solution = (new ShelfKnapsackPacker)->pack($candidates, 30.0);

    expect($solution[1])->toBe(1)   // B entrou
        ->and($solution[0])->toBe(1); // A ficou na frente mínima
});

test('entre opcionais que disputam o mesmo vão, vence o mais bem ranqueado', function (): void {
    // Só um dos dois cabe nos 12cm que sobram. Mesma largura → decide o ranking.
    $candidates = [
        packCandidate(10.0, inclusion: 0.55),
        packCandidate(10.0, inclusion: 1.0),
    ];

    $solution = (new ShelfKnapsackPacker)->pack($candidates, 12.0);

    expect($solution[1])->toBe(1)
        ->and($solution[0])->toBe(0);
});

// ── o ganho de precisão ───────────────────────────────────────────────────────

test('acha o encaixe exato que o round-robin guloso não acha', function (): void {
    /*
     * Prateleira de 100cm, produtos de 7cm e 11cm, até 20 frentes cada.
     *
     * O round-robin do motor antigo alterna +1 frente entre os dois e trava em 6×7 + 5×11 = 97cm,
     * porque a partir dali nem 7 nem 11 cabem nos 3cm restantes. Não existe volta: a decisão de
     * cada frente já foi tomada.
     *
     * O empacotador enxerga a prateleira inteira e acha 8×7 + 4×11 = 100cm — encaixe EXATO.
     * Esses 3cm por prateleira são exatamente o "vão que ninguém preenche" da gôndola.
     */
    $candidates = [
        packCandidate(7.0, min: 1, max: 12, forced: true, inclusion: 1.0, facingWeight: 1.0),
        packCandidate(11.0, min: 1, max: 12, forced: true, inclusion: 1.0, facingWeight: 0.5),
    ];

    $solution = (new ShelfKnapsackPacker)->pack($candidates, 100.0);

    expect(packedWidth($candidates, $solution))->toBe(100.0)
        ->and($solution[0])->toBe(8)
        ->and($solution[1])->toBe(4);
});

test('reconsidera um rejeitado estreito para tapar o vão do fim', function (): void {
    /*
     * O guloso coloca A (30cm) e B (30cm), rejeita C (35cm, não cabe nos 40cm restantes… cabe!)
     * — então o cenário real é o outro: com as frentes expandidas, o vão do fim fica órfão.
     *
     * Aqui: A e B são obrigatórios (o guloso já os colocou) e podem expandir até 3 frentes.
     * O round-robin gastaria a sobra expandindo A e B. O empacotador prefere reservar 8cm para
     * incluir C — mais um SKU na gôndola, que é o que o negócio quer.
     */
    $candidates = [
        packCandidate(30.0, min: 1, max: 3, forced: true, inclusion: 1.0, facingWeight: 1.0),
        packCandidate(30.0, min: 1, max: 3, forced: true, inclusion: 0.9, facingWeight: 0.9),
        packCandidate(8.0, min: 1, max: 1, inclusion: 0.6, facingWeight: 0.1),
    ];

    $solution = (new ShelfKnapsackPacker)->pack($candidates, 68.0);

    expect($solution[0])->toBe(1)
        ->and($solution[1])->toBe(1)
        ->and($solution[2])->toBe(1)   // o rejeitado voltou para o jogo
        ->and(packedWidth($candidates, $solution))->toBe(68.0);
});
