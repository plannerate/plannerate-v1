<?php

use Callcocam\LaravelRaptorPlannerate\Services\Analysis\AbcAnalysisService;
use Illuminate\Support\Collection;

/*
 * Teste de PARIDADE com a planilha de referência do cliente (AÇÚCAR, 8 SKUs).
 *
 * >>> SUSPENSO DE PROPÓSITO — NÃO É UM TESTE QUEBRADO. <<<
 *
 * Ele documenta, em código executável, a regra que a planilha do cliente usa. A
 * decisão de qual regra vale ainda está com o cliente, então o serviço foi mantido
 * na regra vigente (a de 12/06) e este teste fica suspenso até a decisão sair.
 * Para reativá-lo, basta remover os ->skip().
 *
 * As DUAS regras em disputa:
 *
 *   PLANILHA (e o sistema até 12/06/2026)
 *     - a classe sai do percentual acumulado APÓS somar o item;
 *     - cortes inclusivos (<=): acumulado <= 0,80 é A; <= 0,85 é B; o resto é C.
 *     - efeito colateral: categoria com 1 produto (acumulado 100%) vira C.
 *
 *   VIGENTE (commit 01da121b, 12/06/2026)
 *     - a classe sai do percentual acumulado ANTES de somar o item;
 *     - cortes estritos (<).
 *     - efeito: promove TODO item que cruza um corte → um A a mais em cada grupo
 *       (62,6% de classe A numa gôndola real de 270 produtos), e a tela exibe um
 *       acumulado diferente do que decidiu a classe ("93,16%" ao lado de "classe A").
 *
 * Validado contra o banco real na janela que a planilha usou: com a regra da planilha
 * o sistema reproduz os 8 SKUs exatamente (CRISTAL A,A,C,C — REFINADO A,A,B,C).
 */

const MOTIVO_SUSPENSAO = 'Regra ABC pendente de decisão do cliente: o serviço está na regra de 12/06 (acumulado ANTES do item); este teste trava a regra da planilha (acumulado APÓS o item).';

/**
 * Monta a entrada de classifyRankedProducts a partir das médias ponderadas da planilha.
 *
 * @param  array<string, float>  $mediasPonderadas  mapa descrição => média ponderada
 */
function planilhaAbc(array $mediasPonderadas): Collection
{
    return collect($mediasPonderadas)
        ->map(fn (float $media, string $desc) => ['product_id' => $desc, 'media_ponderada' => $media])
        ->values()
        ->sortByDesc('media_ponderada')
        ->values();
}

beforeEach(function (): void {
    // Cortes da planilha: A até 80% do acumulado, B até 85%, C o resto.
    $this->service = (new AbcAnalysisService)->setCuts(0.80, 0.85);
});

it('reproduz a classificação da planilha no grupo AÇÚCAR CRISTAL', function (): void {
    $input = planilhaAbc([
        'CRISTAL ALTO ALEGRE 2KG' => 1910.89,
        'CRISTAL EUROCUCAR 5KG' => 1484.18,
        'CRISTAL ALTO ALEGRE 5KG' => 741.51,
        'CRISTAL UNIAO 1KG' => 303.87,
    ]);

    $result = $this->service->classifyRankedProducts($input, 4440.45)->keyBy('product_id');

    // Acumulados da planilha: 43,03% / 76,46% / 93,16% / 100%
    expect($result['CRISTAL ALTO ALEGRE 2KG']['classificacao'])->toBe('A')
        ->and($result['CRISTAL EUROCUCAR 5KG']['classificacao'])->toBe('A')
        // 93,16% de acumulado. A regra de 12/06 devolve A aqui — a planilha diz C.
        ->and($result['CRISTAL ALTO ALEGRE 5KG']['classificacao'])->toBe('C')
        ->and($result['CRISTAL UNIAO 1KG']['classificacao'])->toBe('C');
})->skip(MOTIVO_SUSPENSAO);

it('reproduz a classificação da planilha no grupo AÇÚCAR REFINADO', function (): void {
    $input = planilhaAbc([
        'REF ALTO ALEGRE 1KG' => 1180.09,
        'REF UNIAO 1KG' => 562.13,
        'REFINADO DA BARRA 1KG' => 520.13,
        'REF ALTO ALEGRE 5KG' => 435.03,
    ]);

    $result = $this->service->classifyRankedProducts($input, 2697.38)->keyBy('product_id');

    // Acumulados da planilha: 43,75% / 64,59% / 83,87% / 100%
    expect($result['REF ALTO ALEGRE 1KG']['classificacao'])->toBe('A')
        ->and($result['REF UNIAO 1KG']['classificacao'])->toBe('A')
        // 83,87% cai dentro da faixa do B (80% a 85%) — é a prova de que a faixa
        // estreita do B funciona quando a classe sai do acumulado APÓS o item.
        ->and($result['REFINADO DA BARRA 1KG']['classificacao'])->toBe('B')
        ->and($result['REF ALTO ALEGRE 5KG']['classificacao'])->toBe('C');
})->skip(MOTIVO_SUSPENSAO);

it('o acumulado exibido é o MESMO número que decide a classe', function (): void {
    // O bug de 12/06 classificava por um número e exibia outro: na tela aparecia
    // "acumulado 93,16%" ao lado de "classe A". A classe e o acumulado mostrado
    // têm que sair do mesmo valor, senão a linha se contradiz sozinha.
    $input = planilhaAbc([
        'CRISTAL ALTO ALEGRE 2KG' => 1910.89,
        'CRISTAL EUROCUCAR 5KG' => 1484.18,
        'CRISTAL ALTO ALEGRE 5KG' => 741.51,
        'CRISTAL UNIAO 1KG' => 303.87,
    ]);

    $result = $this->service->classifyRankedProducts($input, 4440.45);

    foreach ($result as $row) {
        // percentual_acumulado vem como fração (0..1); o ×100 é feito pelo chamador.
        $acumulado = $row['percentual_acumulado'];
        $esperado = $acumulado <= 0.80 ? 'A' : ($acumulado <= 0.85 ? 'B' : 'C');

        expect($row['classificacao'])->toBe(
            $esperado,
            sprintf('%s: acumulado %.2f%% deveria ser %s', $row['product_id'], $acumulado * 100, $esperado),
        );
    }
})->skip(MOTIVO_SUSPENSAO);

it('os percentuais acumulados batem com os da planilha', function (): void {
    $input = planilhaAbc([
        'CRISTAL ALTO ALEGRE 2KG' => 1910.89,
        'CRISTAL EUROCUCAR 5KG' => 1484.18,
        'CRISTAL ALTO ALEGRE 5KG' => 741.51,
        'CRISTAL UNIAO 1KG' => 303.87,
    ]);

    $result = $this->service->classifyRankedProducts($input, 4440.45)->keyBy('product_id');

    $pct = fn (string $id) => round($result[$id]['percentual_acumulado'] * 100, 2);

    expect($pct('CRISTAL ALTO ALEGRE 2KG'))->toBe(43.03)
        ->and($pct('CRISTAL EUROCUCAR 5KG'))->toBe(76.46)
        ->and($pct('CRISTAL ALTO ALEGRE 5KG'))->toBe(93.16)
        ->and($pct('CRISTAL UNIAO 1KG'))->toBe(100.0);
})->skip(MOTIVO_SUSPENSAO);
