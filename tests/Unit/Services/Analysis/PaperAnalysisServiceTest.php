<?php

use Callcocam\LaravelRaptorPlannerate\Services\Analysis\PaperAnalysisService;
use Illuminate\Support\Collection;

/*
 * Testes da etapa pura da Análise de Papel (classifyMetrics).
 *
 * Regras corrigidas:
 *   - limiar de crescimento relativo: mediana dos growth_rate da categoria
 *     (antes era fixo em 0 → anchor/lagging nunca apareciam em alta generalizada)
 *   - produto novo (sem venda anterior) → rising com is_new=true e growth_rate null
 *     (antes ganhava +100% de crescimento artificial)
 */

/**
 * Monta a coleção de entrada no formato produzido por analyzeByProductIds.
 *
 * @param  array<int, array{0: string, 1: string, 2: float, 3: float}>  $rows  [product_id, category_id, valor_atual, valor_anterior]
 */
function paperInput(array $rows): Collection
{
    return collect($rows)->map(fn (array $row) => (object) [
        'product_id' => $row[0],
        'category_id' => $row[1],
        'valor_atual' => $row[2],
        'valor_anterior' => $row[3],
    ]);
}

beforeEach(function (): void {
    $this->service = new PaperAnalysisService;
});

it('mediana de crescimento da categoria separa alto e baixo crescimento (4 papéis presentes)', function (): void {
    // Categoria em alta generalizada: todos os crescimentos positivos.
    // Com limiar fixo 0 todos seriam "alto crescimento"; com a mediana (~22.5%),
    // metade fica abaixo → os 4 papéis aparecem.
    $result = $this->service->classifyMetrics(paperInput([
        ['lider', 'cat1', 400.0, 300.0],   // share alto, growth +33% ≥ mediana → leader
        ['ancora', 'cat1', 300.0, 270.0],  // share alto, growth +11% < mediana → anchor
        ['subindo', 'cat1', 100.0, 75.0],  // share baixo, growth +33% ≥ mediana → rising
        ['atras', 'cat1', 80.0, 70.0],     // share baixo, growth +14% < mediana → lagging
    ]));

    $roles = $result->pluck('role', 'product_id');

    expect($roles['lider'])->toBe('leader')
        ->and($roles['ancora'])->toBe('anchor')
        ->and($roles['subindo'])->toBe('rising')
        ->and($roles['atras'])->toBe('lagging');
});

it('produto novo sem período anterior vira rising com is_new true e growth_rate null', function (): void {
    $result = $this->service->classifyMetrics(paperInput([
        ['veterano', 'cat1', 500.0, 400.0],
        ['novato', 'cat1', 50.0, 0.0],
    ]));

    $novato = $result->firstWhere('product_id', 'novato');

    expect($novato['role'])->toBe('rising')
        ->and($novato['is_new'])->toBeTrue()
        ->and($novato['growth_rate'])->toBeNull();
});

it('produto novo não entra no cálculo da mediana de crescimento', function (): void {
    // Sem o novato, a mediana dos growths é a média de +10% e +50% = +30%.
    // Se o novato (sem growth) entrasse com 100%, a mediana seria +50% e
    // o produto de +50% deixaria de ser "alto crescimento".
    $result = $this->service->classifyMetrics(paperInput([
        ['cresce-pouco', 'cat1', 110.0, 100.0],  // +10%
        ['cresce-muito', 'cat1', 150.0, 100.0],  // +50%
        ['novato', 'cat1', 10.0, 0.0],
    ]));

    $cresceMuito = $result->firstWhere('product_id', 'cresce-muito');

    expect($cresceMuito['growth_threshold'])->toEqualWithDelta(30.0, 0.001)
        ->and($cresceMuito['role'])->toBe('leader'); // share alto + growth ≥ mediana
});

it('setGrowthThreshold com valor fixo desativa a mediana por categoria', function (): void {
    $this->service->setGrowthThreshold(40.0);

    $result = $this->service->classifyMetrics(paperInput([
        ['p1', 'cat1', 130.0, 100.0],  // +30% < 40 → baixo crescimento
        ['p2', 'cat1', 160.0, 100.0],  // +60% ≥ 40 → alto crescimento
    ]));

    $byId = $result->keyBy('product_id');

    expect($byId['p1']['growth_threshold'])->toEqualWithDelta(40.0, 0.001)
        ->and($byId['p1']['role'])->toBe('lagging')   // share baixo (130 < mediana 145)
        ->and($byId['p2']['role'])->toBe('leader');   // share alto + growth alto
});

it('categoria de 1 produto: share igual à mediana classifica como alto share', function (): void {
    $result = $this->service->classifyMetrics(paperInput([
        ['unico', 'cat1', 200.0, 100.0],  // share 100% = mediana; growth +100% = mediana
    ]));

    expect($result->first()['role'])->toBe('leader')
        ->and($result->first()['is_new'])->toBeFalse();
});

it('produto sem venda nos dois períodos é lagging (não rising)', function (): void {
    // Antes da correção: growth 0 ≥ limiar 0 → "alto crescimento" → rising indevido
    $result = $this->service->classifyMetrics(paperInput([
        ['ativo', 'cat1', 100.0, 80.0],
        ['parado', 'cat1', 0.0, 0.0],
    ]));

    $parado = $result->firstWhere('product_id', 'parado');

    expect($parado['role'])->toBe('lagging')
        ->and($parado['is_new'])->toBeFalse()
        ->and($parado['growth_rate'])->toBeNull();
});

it('buildPreviousPeriodFilters desloca o período com a mesma duração', function (): void {
    // Subclasse anônima expõe o método protected para teste isolado
    $service = new class extends PaperAnalysisService
    {
        public function exposed(array $filters, string $tableType): array
        {
            return $this->buildPreviousPeriodFilters($filters, $tableType);
        }
    };

    $monthly = $service->exposed([
        'tenant_id' => 't1',
        'start_month' => '2026-01',
        'end_month' => '2026-04',
    ], 'monthly_summaries');

    $daily = $service->exposed([
        'tenant_id' => 't1',
        'date_from' => '2026-03-01',
        'date_to' => '2026-03-10',
    ], 'sales');

    expect($monthly['start_month'])->toBe('2025-09')
        ->and($monthly['end_month'])->toBe('2025-12')
        ->and($monthly['tenant_id'])->toBe('t1')
        ->and($daily['date_from'])->toBe('2026-02-19')
        ->and($daily['date_to'])->toBe('2026-02-28');
});
