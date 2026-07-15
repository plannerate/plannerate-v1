<?php

use Callcocam\LaravelRaptorPlannerate\Services\Analysis\BcgAnalysisService;
use Illuminate\Support\Collection;

/*
 * Testes da etapa pura da Análise BCG (classifyQuadrants).
 *
 * A BCG classifica em 2 eixos de NÍVEL num único período — sem eixo de crescimento
 * e sem período anterior (é o que a separa da Análise de Papel).
 *
 * Divergências deliberadas do VBA cobertas aqui:
 *   - corte pela MEDIANA por padrão (o VBA usa média; disponível via setThresholdMethod)
 *   - produto sem venda fica FORA do cálculo do limiar (mas continua no resultado)
 *   - não existe quadrante "descontinuar": as chaves são agnósticas de eixo
 */

/**
 * Monta a coleção de entrada no formato produzido por analyzeByProductIds.
 *
 * @param  array<int, array{0: string, 1: string, 2: float, 3: float, 4?: bool}>  $rows  [product_id, group_id, x_value, y_value, sem_venda?]
 */
function bcgInput(array $rows): Collection
{
    return collect($rows)->map(fn (array $row) => (object) [
        'product_id' => $row[0],
        'group_id' => $row[1],
        'x_value' => $row[2],
        'y_value' => $row[3],
        'sem_venda' => $row[4] ?? false,
    ]);
}

beforeEach(function (): void {
    // Eixos padrão: X = quantidade, Y = margem (o preset canônico da planilha)
    $this->service = new BcgAnalysisService;
});

it('mediana do grupo separa os quatro quadrantes', function (): void {
    // X: [100, 100, 10, 10] → mediana 55 | Y: [100, 10, 100, 10] → mediana 55
    $result = $this->service->classifyQuadrants(bcgInput([
        ['forte-nos-dois', 'cat1', 100.0, 100.0],
        ['so-volume', 'cat1', 100.0, 10.0],
        ['so-margem', 'cat1', 10.0, 100.0],
        ['fraco', 'cat1', 10.0, 10.0],
    ]));

    $quadrants = $result->pluck('quadrant', 'product_id');

    expect($quadrants['forte-nos-dois'])->toBe('alto_alto')
        ->and($quadrants['so-volume'])->toBe('forte_x')
        ->and($quadrants['so-margem'])->toBe('forte_y')
        ->and($quadrants['fraco'])->toBe('baixo_baixo')
        ->and($result->firstWhere('product_id', 'forte-nos-dois')['x_threshold'])->toEqualWithDelta(55.0, 0.001);
});

it('corte pela média (VBA) é puxado pelo outlier e rebaixa quem a mediana promove', function (): void {
    // Cauda longa clássica de varejo — X: [10, 20, 30, 940]
    //   mediana = 25   → o produto de 30 fica ACIMA do corte
    //   média   = 250  → o mesmo produto cai ABAIXO, arrastado pelo líder
    $input = bcgInput([
        ['p10', 'cat1', 10.0, 50.0],
        ['p20', 'cat1', 20.0, 50.0],
        ['p30', 'cat1', 30.0, 50.0],
        ['lider', 'cat1', 940.0, 50.0],
    ]);

    $comMediana = $this->service->classifyQuadrants($input);
    $comMedia = (new BcgAnalysisService)->setThresholdMethod(BcgAnalysisService::THRESHOLD_MEAN)
        ->classifyQuadrants($input);

    expect($comMediana->firstWhere('product_id', 'p30')['x_threshold'])->toEqualWithDelta(25.0, 0.001)
        ->and($comMediana->firstWhere('product_id', 'p30')['quadrant'])->toBe('alto_alto')
        ->and($comMedia->firstWhere('product_id', 'p30')['x_threshold'])->toEqualWithDelta(250.0, 0.001)
        ->and($comMedia->firstWhere('product_id', 'p30')['quadrant'])->toBe('forte_y');
});

it('produto sem venda entra no resultado mas fica fora do cálculo do limiar', function (): void {
    // Ativos → X: [100, 50] → mediana 75, e o produto de 50 fica ABAIXO do corte.
    // Se o item morto (0) entrasse na estatística, a mediana cairia para 50 e ele
    // seria promovido a "alto" — exatamente o efeito que a exclusão evita.
    $result = $this->service->classifyQuadrants(bcgInput([
        ['ativo-forte', 'cat1', 100.0, 100.0],
        ['ativo-medio', 'cat1', 50.0, 50.0],
        ['morto', 'cat1', 0.0, 0.0, true],
    ]));

    $byId = $result->keyBy('product_id');

    expect($byId['ativo-medio']['x_threshold'])->toEqualWithDelta(75.0, 0.001)
        ->and($byId['ativo-medio']['quadrant'])->toBe('baixo_baixo')
        ->and($byId['morto']['quadrant'])->toBe('baixo_baixo')
        ->and($byId['morto']['sem_venda'])->toBeTrue()
        ->and($byId['morto']['x_percentil'])->toBe(0.0)
        ->and($result)->toHaveCount(3);
});

it('grupo de um único produto: valor igual ao limiar classifica como alto (>= inclusivo)', function (): void {
    $result = $this->service->classifyQuadrants(bcgInput([
        ['unico', 'cat1', 10.0, 5.0],
    ]));

    expect($result->first()['quadrant'])->toBe('alto_alto')
        ->and($result->first()['sem_venda'])->toBeFalse();
});

it('margem negativa entra na estatística e levanta alerta', function (): void {
    // Y = margem: [-50, 20] → mediana -15. O produto de margem -50 vende muito
    // (alto X) e cairia em forte_x sem chamar atenção — daí o alerta.
    $result = $this->service->classifyQuadrants(bcgInput([
        ['vende-com-prejuizo', 'cat1', 100.0, -50.0],
        ['saudavel', 'cat1', 10.0, 20.0],
    ]));

    $byId = $result->keyBy('product_id');

    expect($byId['vende-com-prejuizo']['alerta_margem_negativa'])->toBeTrue()
        ->and($byId['vende-com-prejuizo']['quadrant'])->toBe('forte_x')
        ->and($byId['saudavel']['alerta_margem_negativa'])->toBeFalse();
});

it('is_borderline acende para quem está em cima da linha de corte', function (): void {
    // X: [10, 50, 90] → mediana 50, amplitude 80 → borda = 10% de 80 = 8
    // Y constante → amplitude 0 → nunca gera borda (ninguém é "quase" nada)
    $result = $this->service->classifyQuadrants(bcgInput([
        ['no-limite', 'cat1', 50.0, 100.0],
        ['bem-abaixo', 'cat1', 10.0, 100.0],
        ['bem-acima', 'cat1', 90.0, 100.0],
    ]));

    $byId = $result->keyBy('product_id');

    expect($byId['no-limite']['is_borderline'])->toBeTrue()
        ->and($byId['bem-abaixo']['is_borderline'])->toBeFalse()
        ->and($byId['bem-acima']['is_borderline'])->toBeFalse();
});

it('percentil dá o score contínuo por trás do rótulo do quadrante', function (): void {
    // X: [10, 10, 100, 100] — o rótulo diz só "alto/baixo"; o percentil diz onde.
    $result = $this->service->classifyQuadrants(bcgInput([
        ['topo', 'cat1', 100.0, 100.0],
        ['topo-empatado', 'cat1', 100.0, 100.0],
        ['base', 'cat1', 10.0, 10.0],
        ['base-empatado', 'cat1', 10.0, 10.0],
    ]));

    $byId = $result->keyBy('product_id');

    expect($byId['topo']['x_percentil'])->toEqualWithDelta(100.0, 0.001)
        ->and($byId['base']['x_percentil'])->toEqualWithDelta(50.0, 0.001);
});

it('limiares são calculados por grupo, não globalmente', function (): void {
    // cat1 opera em outra escala que cat2: um produto forte na cat2 seria fraco na cat1.
    $result = $this->service->classifyQuadrants(bcgInput([
        ['cat1-a', 'cat1', 1000.0, 1000.0],
        ['cat1-b', 'cat1', 500.0, 500.0],
        ['cat2-a', 'cat2', 10.0, 10.0],
        ['cat2-b', 'cat2', 5.0, 5.0],
    ]));

    $byId = $result->keyBy('product_id');

    // cat2-a é "alto" no seu grupo apesar de valer 100x menos que o menor da cat1
    expect($byId['cat2-a']['quadrant'])->toBe('alto_alto')
        ->and($byId['cat2-a']['x_threshold'])->toEqualWithDelta(7.5, 0.001)
        ->and($byId['cat1-b']['x_threshold'])->toEqualWithDelta(750.0, 0.001);
});

it('grupo inteiro sem venda não quebra e cai todo em baixo_baixo', function (): void {
    $result = $this->service->classifyQuadrants(bcgInput([
        ['morto-1', 'cat1', 0.0, 0.0, true],
        ['morto-2', 'cat1', 0.0, 0.0, true],
    ]));

    expect($result->pluck('quadrant')->unique()->all())->toBe(['baixo_baixo'])
        ->and($result->first()['x_threshold'])->toBe(0.0);
});

it('eixos escolhidos são devolvidos no resultado para a UI compor o rótulo', function (): void {
    // Os 4 nomes do VBA só valem para X=quantidade/Y=margem; com eixos configuráveis
    // o rótulo tem de ser derivado, então o backend precisa dizer quais eixos usou.
    $result = $this->service->setAxes('valor', 'quantidade')->classifyQuadrants(bcgInput([
        ['p1', 'cat1', 100.0, 10.0],
    ]));

    expect($result->first()['x_axis'])->toBe('valor')
        ->and($result->first()['y_axis'])->toBe('quantidade')
        // sem eixo de margem, o alerta de prejuízo não se aplica
        ->and($result->first()['alerta_margem_negativa'])->toBeFalse();
});

it('cruza quadrante com espaço: alto valor espremido pede mais frentes, fraco inchado pede menos', function (): void {
    // O quadrante sozinho não é acionável — só o cruzamento com o espaço ocupado é.
    // Eixos: [100, 100, 10, 10] → mediana 55, então os dois de 10 ficam em baixo_baixo.
    $classified = $this->service->classifyQuadrants(bcgInput([
        ['campeao-espremido', 'cat1', 100.0, 100.0],  // alto_alto, pouco espaço
        ['campeao-servido', 'cat1', 100.0, 100.0],    // alto_alto, espaço adequado
        ['fraco-inchado', 'cat1', 10.0, 10.0],        // baixo_baixo, muito espaço
        ['fraco-discreto', 'cat1', 10.0, 10.0],       // baixo_baixo, pouco espaço
    ]));

    // Shares: [2, 20, 60, 5] → mediana 12,5. Só quem foge da mediana gera ação.
    $result = $this->service->withSpace($classified, [
        'campeao-espremido' => ['facings' => 1, 'espaco_linear_cm' => 10.0, 'share_gondola' => 2.0, 'sem_dimensao' => false],
        'campeao-servido' => ['facings' => 4, 'espaco_linear_cm' => 100.0, 'share_gondola' => 20.0, 'sem_dimensao' => false],
        'fraco-inchado' => ['facings' => 12, 'espaco_linear_cm' => 300.0, 'share_gondola' => 60.0, 'sem_dimensao' => false],
        'fraco-discreto' => ['facings' => 1, 'espaco_linear_cm' => 10.0, 'share_gondola' => 5.0, 'sem_dimensao' => false],
    ])->keyBy('product_id');

    expect($result['campeao-espremido']['acao_espaco'])->toBe('aumentar')
        ->and($result['fraco-inchado']['acao_espaco'])->toBe('reduzir')
        // ser forte não basta para ganhar frente: este já tem espaço à altura
        ->and($result['campeao-servido']['acao_espaco'])->toBe('manter')
        // fraco, mas já ocupa pouco: não há espaço a recuperar
        ->and($result['fraco-discreto']['acao_espaco'])->toBe('manter')
        ->and($result['fraco-inchado']['facings'])->toBe(12)
        ->and($result['fraco-inchado']['share_gondola'])->toBe(60.0);
});

it('produto sem dimensão não recebe ação de espaço nem entra no corte de share', function (): void {
    // Sem largura, o share é 0 por falta de dado — não porque o produto seja pequeno.
    // Recomendar "aumentar frentes" aqui seria um palpite disfarçado de análise.
    $classified = $this->service->classifyQuadrants(bcgInput([
        ['campeao-sem-medida', 'cat1', 100.0, 100.0],
        ['normal', 'cat1', 10.0, 10.0],
    ]));

    $result = $this->service->withSpace($classified, [
        'campeao-sem-medida' => ['facings' => 3, 'espaco_linear_cm' => 0.0, 'share_gondola' => 0.0, 'sem_dimensao' => true],
        'normal' => ['facings' => 2, 'espaco_linear_cm' => 20.0, 'share_gondola' => 100.0, 'sem_dimensao' => false],
    ])->keyBy('product_id');

    expect($result['campeao-sem-medida']['acao_espaco'])->toBeNull()
        // o corte de share ignora o item sem dimensão: mediana de [100] = 100, não de [0, 100] = 50
        ->and($result['normal']['share_threshold_gondola'])->toEqualWithDelta(100.0, 0.001);
});

it('rejeita eixos iguais, eixo desconhecido, corte e nível de classificação inválidos', function (): void {
    // Eixos iguais colapsam a matriz numa diagonal: só sobram alto_alto e baixo_baixo.
    expect(fn () => (new BcgAnalysisService)->setAxes('margem', 'margem'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => (new BcgAnalysisService)->setAxes('gmroi', 'margem'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => (new BcgAnalysisService)->setThresholdMethod('moda'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => (new BcgAnalysisService)->setClassifyBy('produto'))
        ->toThrow(InvalidArgumentException::class);
});

it('display_by aceita produto e qualquer nível abaixo do corte, rejeitando os demais', function (): void {
    $service = new BcgAnalysisService;

    // Padrão é por produto (nível mais profundo, sempre válido)
    expect($service->getDisplayBy())->toBe('produto');
    expect((new BcgAnalysisService)->setClassifyBy('subcategoria')->setDisplayBy('produto')->getDisplayBy())
        ->toBe('produto');

    // Modo desconhecido é rejeitado
    expect(fn () => (new BcgAnalysisService)->setDisplayBy('gondola'))
        ->toThrow(InvalidArgumentException::class);

    // Exibir num nível igual ou acima do corte deixaria cada grupo sozinho — rejeitado.
    expect(fn () => (new BcgAnalysisService)->setClassifyBy('categoria')->setDisplayBy('categoria'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => (new BcgAnalysisService)->setClassifyBy('categoria')->setDisplayBy('departamento'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => (new BcgAnalysisService)->setClassifyBy('subcategoria')->setDisplayBy('categoria'))
        ->toThrow(InvalidArgumentException::class);

    // Qualquer nível estritamente abaixo do corte é válido
    expect((new BcgAnalysisService)->setClassifyBy('departamento')->setDisplayBy('categoria')->getDisplayBy())
        ->toBe('categoria')
        ->and((new BcgAnalysisService)->setClassifyBy('segmento_varejista')->setDisplayBy('departamento')->getDisplayBy())
        ->toBe('departamento')
        ->and((new BcgAnalysisService)->setClassifyBy('departamento')->setDisplayBy('subcategoria')->getDisplayBy())
        ->toBe('subcategoria');
});

it('withSpace soma o espaço dos produtos da categoria e corta pela mediana das categorias', function (): void {
    // Resultado agregado: cada linha é uma categoria com member_product_ids.
    // catA (alto_alto) soma pouco espaço; catB (baixo_baixo) ocupa muito.
    $classified = collect([
        ['product_id' => 'catA', 'quadrant' => 'alto_alto', 'member_product_ids' => ['p1', 'p2']],
        ['product_id' => 'catB', 'quadrant' => 'baixo_baixo', 'member_product_ids' => ['p3']],
    ]);

    // Shares por categoria: catA = 5+5 = 10, catB = 40 → mediana 25.
    $result = $this->service->withSpace($classified, [
        'p1' => ['facings' => 1, 'espaco_linear_cm' => 10.0, 'share_gondola' => 5.0, 'sem_dimensao' => false],
        'p2' => ['facings' => 2, 'espaco_linear_cm' => 20.0, 'share_gondola' => 5.0, 'sem_dimensao' => false],
        'p3' => ['facings' => 10, 'espaco_linear_cm' => 100.0, 'share_gondola' => 40.0, 'sem_dimensao' => false],
    ])->keyBy('product_id');

    expect($result['catA']['facings'])->toBe(3)
        ->and($result['catA']['espaco_linear_cm'])->toEqualWithDelta(30.0, 0.001)
        ->and($result['catA']['share_gondola'])->toEqualWithDelta(10.0, 0.001)
        // alto_alto espremido (10 < 25) → aumentar
        ->and($result['catA']['acao_espaco'])->toBe('aumentar')
        // baixo_baixo inchado (40 > 25) → reduzir
        ->and($result['catB']['acao_espaco'])->toBe('reduzir')
        ->and($result['catA']['share_threshold_gondola'])->toEqualWithDelta(25.0, 0.001)
        // detalhe interno do agregado não vaza para o resultado
        ->and($result['catA'])->not->toHaveKey('member_product_ids');
});

it('categoria sem nenhum produto com largura fica sem dimensão e sem ação de espaço', function (): void {
    $classified = collect([
        ['product_id' => 'catA', 'quadrant' => 'alto_alto', 'member_product_ids' => ['p1', 'p2']],
        ['product_id' => 'catB', 'quadrant' => 'baixo_baixo', 'member_product_ids' => ['p3']],
    ]);

    $result = $this->service->withSpace($classified, [
        // catA: nenhum membro tem largura cadastrada
        'p1' => ['facings' => 1, 'espaco_linear_cm' => 0.0, 'share_gondola' => 0.0, 'sem_dimensao' => true],
        'p2' => ['facings' => 1, 'espaco_linear_cm' => 0.0, 'share_gondola' => 0.0, 'sem_dimensao' => true],
        'p3' => ['facings' => 5, 'espaco_linear_cm' => 50.0, 'share_gondola' => 30.0, 'sem_dimensao' => false],
    ])->keyBy('product_id');

    expect($result['catA']['sem_dimensao'])->toBeTrue()
        ->and($result['catA']['acao_espaco'])->toBeNull()
        // mediana do share ignora catA (sem dimensão): mediana de [30] = 30
        ->and($result['catB']['share_threshold_gondola'])->toEqualWithDelta(30.0, 0.001);
});

it('classify_by aceita os cinco níveis da hierarquia mercadológica', function (): void {
    $service = new BcgAnalysisService;

    // 'produto' NÃO é um nível de classificação: é onde o resultado é exibido.
    // O grupo de comparação é sempre uma categoria ancestral.
    expect(array_keys(BcgAnalysisService::HIERARCHY_LEVELS))->toBe([
        'segmento_varejista',
        'departamento',
        'subdepartamento',
        'categoria',
        'subcategoria',
    ])
        ->and($service->getClassifyBy())->toBe('categoria')
        ->and($service->setClassifyBy('departamento')->getClassifyBy())->toBe('departamento');
});
