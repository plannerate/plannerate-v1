<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Services\Analysis;

use Callcocam\LaravelRaptorPlannerate\Models\Category;
use Callcocam\LaravelRaptorPlannerate\Models\Layer;
use Callcocam\LaravelRaptorPlannerate\Models\Product;
use Callcocam\LaravelRaptorPlannerate\Sales\ProductSalesAggregateQuery;
use Callcocam\LaravelRaptorPlannerate\Sales\SalesStatistics;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Service da Análise de Quadrante (matriz de quadrantes com eixos configuráveis).
 *
 * Porta da macro VBA de docs/BCG.md, com divergências deliberadas (ver abaixo).
 * Classifica cada produto comparando-o ao seu grupo mercadológico em DOIS eixos de
 * NÍVEL escolhidos entre valor de venda, quantidade e margem de contribuição.
 *
 * FRONTEIRA COM A ANÁLISE DE PAPEL — as duas seriam redundantes sem isto:
 *   - Paper: share × CRESCIMENTO, dois períodos. Responde "para onde o produto vai?"
 *   - BCG:   nível × nível, UM período. Responde "quanto o produto vale hoje?"
 * Por isso o BCG não busca período anterior e não tem eixo de crescimento.
 *
 * Quadrantes (chaves estáveis, agnósticas de eixo — ver nota sobre rótulos):
 *   - alto_alto   : forte nos dois eixos
 *   - forte_x     : forte só no eixo X
 *   - forte_y     : forte só no eixo Y
 *   - baixo_baixo : fraco nos dois
 *
 * DIVERGÊNCIAS DELIBERADAS DO VBA:
 *
 * 1. CORTE PELA MEDIANA por padrão (o VBA usa a média). Vendas de varejo têm cauda
 *    longa: a média é puxada por poucos líderes e joga quase todo o sortimento para
 *    baixo dela. A média continua disponível via setThresholdMethod('mean') para
 *    reproduzir a planilha número a número.
 *
 * 2. PRODUTO SEM VENDA FICA FORA DO CÁLCULO DO LIMIAR. Ele entra no resultado (senão
 *    sumiria da gôndola) zerado e já em 'baixo_baixo', mas não contamina a estatística
 *    do grupo — uma gôndola cheia de itens mortos arrastaria o limiar para baixo e
 *    faria produtos medíocres parecerem fortes. Mesma lógica com que o Paper exclui
 *    produtos novos da mediana de crescimento.
 *
 * 3. RÓTULOS NÃO FICAM NO BACKEND. Os 4 nomes do VBA ("Incentivo – volume", etc.) só
 *    fazem sentido se X=quantidade e Y=margem; com eixos configuráveis eles mentem
 *    (X=valor e Y=quantidade não tem eixo de lucro algum). O backend devolve a chave
 *    estável do quadrante + os eixos usados, e a UI compõe o rótulo.
 *
 * 4. NÃO EXISTE QUADRANTE "DESCONTINUAR". A evidência de mercado é de que ler ação
 *    direto do quadrante degrada a decisão. O BCG é diagnóstico; a descontinuação
 *    continua sendo prerrogativa da Análise ABC (retirar_do_mix), que tem regra própria.
 *
 * Testes: tests/Unit/Services/Analysis/BcgAnalysisServiceTest.php
 */
class BcgAnalysisService
{
    /** Eixos disponíveis → coluna somável (presente em sales e monthly_sales_summaries). */
    public const AXIS_COLUMNS = [
        'valor' => 'total_sale_value',
        'quantidade' => 'total_sale_quantity',
        'margem' => 'margem_contribuicao',
    ];

    /**
     * Nível da hierarquia mercadológica → índice na cadeia raiz→folha.
     *
     * Define ONDE a linha de corte é calculada: classificar por 'departamento' compara
     * cada produto com todos os produtos do seu departamento; por 'subcategoria',
     * apenas com os seus pares diretos. Quanto mais alto o nível, maior (e mais
     * heterogêneo) o grupo de comparação.
     */
    public const HIERARCHY_LEVELS = [
        'segmento_varejista' => 0,
        'departamento' => 1,
        'subdepartamento' => 2,
        'categoria' => 3,
        'subcategoria' => 4,
    ];

    public const THRESHOLD_MEDIAN = 'median';

    public const THRESHOLD_MEAN = 'mean';

    /**
     * Granularidade da EXIBIÇÃO dos resultados (distinta de classify_by, que é onde o
     * corte é calculado):
     *   - 'produto'          : uma linha por produto (padrão, nível mais profundo)
     *   - um nível da hierarquia (categoria, departamento, ...): os produtos são somados
     *     no seu ancestral naquele nível e cada grupo é classificado como um item único
     *
     * Exibir num nível exige classificar ACIMA dele (ver setDisplayBy): senão cada grupo
     * ficaria sozinho no seu próprio grupo de corte.
     */
    public const DISPLAY_PRODUTO = 'produto';

    /**
     * Fração da amplitude do grupo dentro da qual um item é considerado "em cima da
     * linha" de corte. Um item a menos de 10% da dispersão do grupo do limiar pode
     * trocar de quadrante no próximo período por ruído, não por mudança real.
     */
    private const BORDERLINE_RATIO = 0.10;

    private string $xAxis = 'quantidade';

    private string $yAxis = 'margem';

    private string $thresholdMethod = self::THRESHOLD_MEDIAN;

    private string $classifyBy = 'categoria';

    private string $displayBy = self::DISPLAY_PRODUTO;

    /**
     * Define as métricas dos eixos X e Y.
     *
     * @throws \InvalidArgumentException Se um eixo for desconhecido ou se X e Y forem iguais
     *                                   (eixos iguais colapsam a matriz numa diagonal, onde só
     *                                   existem os quadrantes alto/alto e baixo/baixo).
     */
    public function setAxes(string $xAxis, string $yAxis): self
    {
        foreach ([$xAxis, $yAxis] as $axis) {
            if (! isset(self::AXIS_COLUMNS[$axis])) {
                throw new \InvalidArgumentException(
                    "Eixo inválido para a Análise de Quadrante: '{$axis}'. Válidos: ".implode(', ', array_keys(self::AXIS_COLUMNS))
                );
            }
        }

        if ($xAxis === $yAxis) {
            throw new \InvalidArgumentException(
                "Os eixos X e Y da Análise de Quadrante devem ser métricas diferentes (ambos são '{$xAxis}')."
            );
        }

        $this->xAxis = $xAxis;
        $this->yAxis = $yAxis;

        return $this;
    }

    /**
     * Define o método de corte dos quadrantes: 'median' (padrão) ou 'mean' (planilha VBA).
     *
     * @throws \InvalidArgumentException Se o método for desconhecido
     */
    public function setThresholdMethod(string $method): self
    {
        if (! in_array($method, [self::THRESHOLD_MEDIAN, self::THRESHOLD_MEAN], true)) {
            throw new \InvalidArgumentException(
                "Método de corte inválido para a Análise de Quadrante: '{$method}'. Válidos: median, mean."
            );
        }

        $this->thresholdMethod = $method;

        return $this;
    }

    /**
     * Define o nível da hierarquia mercadológica onde a linha de corte é calculada.
     *
     * @throws \InvalidArgumentException Se o nível for desconhecido
     */
    public function setClassifyBy(string $level): self
    {
        if (! isset(self::HIERARCHY_LEVELS[$level])) {
            throw new \InvalidArgumentException(
                "Nível de classificação inválido para a Análise de Quadrante: '{$level}'. Válidos: ".implode(', ', array_keys(self::HIERARCHY_LEVELS))
            );
        }

        $this->classifyBy = $level;

        return $this;
    }

    /**
     * Define a granularidade de exibição: 'produto' (padrão) ou um nível da hierarquia
     * mercadológica (categoria, subcategoria, departamento, ...).
     *
     * REGRA QUE PRESERVA O CÁLCULO: exibir num nível exige classificar ESTRITAMENTE
     * ACIMA dele. 'produto' é o nível mais profundo e é sempre válido; qualquer outro
     * nível precisa ficar abaixo de classify_by. Sem isso, cada grupo exibido ficaria
     * sozinho no seu próprio grupo de corte e o limiar seria o próprio valor — jogando
     * tudo para 'alto_alto'. A UI espelha esta mesma restrição.
     *
     * @throws \InvalidArgumentException Se o modo for desconhecido ou não estiver abaixo do nível de corte
     */
    public function setDisplayBy(string $mode): self
    {
        $validModes = array_merge([self::DISPLAY_PRODUTO], array_keys(self::HIERARCHY_LEVELS));

        if (! in_array($mode, $validModes, true)) {
            throw new \InvalidArgumentException(
                "Modo de exibição inválido para a Análise de Quadrante: '{$mode}'. Válidos: ".implode(', ', $validModes).'.'
            );
        }

        // 'produto' é o nível mais profundo (sempre abaixo de qualquer corte). Os demais
        // precisam de índice maior que o de classify_by (mais fundo na hierarquia).
        if ($mode !== self::DISPLAY_PRODUTO
            && self::HIERARCHY_LEVELS[$mode] <= self::HIERARCHY_LEVELS[$this->classifyBy]) {
            throw new \InvalidArgumentException(
                "Para exibir por '{$mode}', classifique por um nível acima de '{$mode}'."
            );
        }

        $this->displayBy = $mode;

        return $this;
    }

    public function getDisplayBy(): string
    {
        return $this->displayBy;
    }

    public function getXAxis(): string
    {
        return $this->xAxis;
    }

    public function getYAxis(): string
    {
        return $this->yAxis;
    }

    public function getClassifyBy(): string
    {
        return $this->classifyBy;
    }

    /**
     * Executa a análise BCG para os produtos alocados na gôndola.
     *
     * @param  array  $productIds  IDs dos produtos fisicamente alocados na gôndola
     * @param  string  $tableType  Fonte dos dados: 'sales' ou 'monthly_summaries'
     * @param  array  $filters  Filtros do período (obrigatório: tenant_id)
     */
    public function analyzeByProductIds(
        array $productIds,
        string $tableType = 'sales',
        array $filters = []
    ): Collection {
        if (empty($productIds)) {
            Log::warning('BcgAnalysis - Lista de product_ids vazia');

            return collect();
        }

        if (! isset($filters['tenant_id']) || empty($filters['tenant_id'])) {
            Log::error('BcgAnalysis - tenant_id é obrigatório');
            throw new \InvalidArgumentException('tenant_id é obrigatório para a Análise de Quadrante');
        }

        // Busca codigo_erp dos produtos para join com as tabelas de venda
        $codigosErp = Product::query()
            ->whereIn('id', $productIds)
            ->whereNotNull('codigo_erp')
            ->pluck('codigo_erp')
            ->toArray();

        if (empty($codigosErp)) {
            Log::warning('BcgAnalysis - Nenhum codigo_erp encontrado para os produtos');

            return collect();
        }

        Log::info('BcgAnalysis - Iniciando cálculo', [
            'tenant_id' => $filters['tenant_id'],
            'table_type' => $tableType,
            'product_count' => count($productIds),
            'x_axis' => $this->xAxis,
            'y_axis' => $this->yAxis,
            'threshold_method' => $this->thresholdMethod,
        ]);

        $productsData = Product::with(['category'])->whereIn('id', $productIds)->get()->keyBy('id');

        // Grupo de comparação de cada produto: a categoria ancestral no nível escolhido
        // em classify_by. É este grupo — e não a categoria folha — que define onde a
        // linha de corte é calculada.
        [$groupIdByProduct, $groupNames] = $this->resolveGroups($productsData, $this->classifyBy);

        $salesData = $this->getSalesData($codigosErp, $productIds, $tableType, $filters);

        $combined = $salesData->map(fn ($row) => (object) [
            'product_id' => $row->product_id,
            'group_id' => $groupIdByProduct[$row->product_id] ?? null,
            'x_value' => (float) ($row->x_value ?? 0),
            'y_value' => (float) ($row->y_value ?? 0),
            'sem_venda' => false,
        ]);

        // Produtos sem venda no período entram zerados — senão sumiriam da gôndola.
        // Ficam marcados para não contaminar o limiar do grupo (ver nota de divergência 2).
        $productsWithSales = $salesData->pluck('product_id')->toArray();
        $productsWithoutSales = array_diff($productIds, $productsWithSales);

        if (! empty($productsWithoutSales)) {
            $zeroRecords = collect($productsWithoutSales)
                ->map(fn ($productId) => (object) [
                    'product_id' => $productId,
                    'group_id' => $groupIdByProduct[$productId] ?? null,
                    'x_value' => 0.0,
                    'y_value' => 0.0,
                    'sem_venda' => true,
                ]);

            $combined = $combined->merge($zeroRecords);
        }

        // Exibir por um nível da hierarquia: soma os produtos no seu ancestral daquele
        // nível e classifica cada grupo como um item único, no mesmo formato de resultado.
        if ($this->displayBy !== self::DISPLAY_PRODUTO) {
            [$displayIdByProduct, $displayNames] = $this->resolveGroups($productsData, $this->displayBy);

            return $this->aggregateByLevel($combined, $displayIdByProduct, $displayNames, $groupNames);
        }

        // Etapa pura: limiares, percentis e quadrantes (testável sem banco)
        $classified = $this->classifyQuadrants($combined);

        return $classified->map(function (array $item) use ($productsData, $groupNames) {
            $product = $productsData->get($item['product_id']);

            return array_merge($item, [
                'product_name' => $product?->name ?? '',
                'ean' => $product?->ean ?? '',
                'image_url' => $product?->image_url ?? null,
                // Categoria folha do produto (exibição) — distinta do grupo do corte
                'category_id' => $product?->category_id,
                'category_name' => $product?->category?->name ?? '',
                'classify_by' => $this->classifyBy,
                'group_name' => $groupNames[$item['group_id']] ?? '',
            ]);
        })->values()->tap(function ($results) {
            Log::info('BcgAnalysis - Resultado final', [
                'total' => $results->count(),
                'alto_alto' => $results->where('quadrant', 'alto_alto')->count(),
                'forte_x' => $results->where('quadrant', 'forte_x')->count(),
                'forte_y' => $results->where('quadrant', 'forte_y')->count(),
                'baixo_baixo' => $results->where('quadrant', 'baixo_baixo')->count(),
                'sem_venda' => $results->where('sem_venda', true)->count(),
                'borderline' => $results->where('is_borderline', true)->count(),
            ]);
        });
    }

    /**
     * Colapsa os produtos no seu ancestral do nível de exibição e classifica cada grupo
     * como um item único, no mesmo formato de resultado do modo por produto.
     *
     * A soma acontece ANTES da classificação: os limiares passam a ser calculados sobre
     * os grupos agregados (não sobre produtos), que é o que a exibição por nível significa.
     * Como o nível de exibição é sempre mais fundo que o de corte, todos os produtos de um
     * grupo exibido compartilham o mesmo ancestral de corte — o group_id é herdado do
     * primeiro produto sem ambiguidade.
     *
     * @param  Collection  $combined  Itens por produto (product_id, group_id, x_value, y_value, sem_venda)
     * @param  array<string, string|null>  $displayIdByProduct  [product_id => grupo_exibicao_id]
     * @param  array<string, string>  $displayNames  [grupo_exibicao_id => nome]
     * @param  array<string, string>  $groupNames  [group_id => nome] do nível de corte
     */
    private function aggregateByLevel(
        Collection $combined,
        array $displayIdByProduct,
        array $displayNames,
        array $groupNames
    ): Collection {
        $aggregated = $combined
            ->groupBy(fn ($item) => $displayIdByProduct[$item->product_id] ?? '__sem_grupo__')
            ->map(function (Collection $items, $displayId) {
                $comVenda = $items->reject(fn ($i) => $i->sem_venda);

                return (object) [
                    'product_id' => (string) $displayId,
                    'group_id' => $items->first()->group_id,
                    'x_value' => (float) $items->sum('x_value'),
                    'y_value' => (float) $items->sum('y_value'),
                    // Grupo só fica "sem venda" se NENHUM produto vendeu no período
                    'sem_venda' => $comVenda->isEmpty(),
                    'member_product_ids' => $items->pluck('product_id')->all(),
                ];
            })
            ->values();

        $membersByGroup = $aggregated->keyBy('product_id');

        return $this->classifyQuadrants($aggregated)
            ->map(function (array $item) use ($displayNames, $groupNames, $membersByGroup) {
                $displayId = $item['product_id'];

                return array_merge($item, [
                    'product_name' => $displayNames[$displayId] ?? '',
                    'ean' => '',
                    'image_url' => null,
                    'category_id' => $displayId,
                    // Sem folha individual: o grupo de corte (departamento, etc.) vira o contexto
                    'category_name' => $groupNames[$item['group_id']] ?? '',
                    'classify_by' => $this->classifyBy,
                    'display_by' => $this->displayBy,
                    'group_name' => $groupNames[$item['group_id']] ?? '',
                    // Consumido por withSpace para somar o espaço dos produtos do grupo
                    'member_product_ids' => $membersByGroup[$displayId]->member_product_ids ?? [],
                ]);
            })
            ->values();
    }

    /**
     * Etapa pura da Análise de Quadrante: calcula os limiares do grupo, o percentil de cada
     * produto e o quadrante. Não consulta o banco.
     *
     * Regras:
     *   - limiar = mediana (padrão) ou média dos valores do grupo em cada eixo,
     *     considerando APENAS produtos com venda no período
     *   - comparação inclusiva (>=): um produto exatamente no limiar é "alto",
     *     o que faz o único produto de um grupo cair em alto_alto (idem Paper)
     *   - produto sem venda → 'baixo_baixo' direto, fora do cálculo do limiar
     *   - is_borderline: valor a menos de 10% da amplitude do grupo do limiar,
     *     em qualquer um dos dois eixos
     *
     * @param  Collection  $combined  Itens com product_id, group_id, x_value, y_value, sem_venda
     * @return Collection<int, array{product_id: mixed, group_id: mixed, quadrant: string, sem_venda: bool, x_axis: string, y_axis: string, x_value: float, y_value: float, x_threshold: float, y_threshold: float, x_percentil: float, y_percentil: float, is_borderline: bool, alerta_margem_negativa: bool}>
     */
    public function classifyQuadrants(Collection $combined): Collection
    {
        $byGroup = $combined->groupBy('group_id');

        // Limiares e amplitudes por grupo, calculados só sobre o sortimento ATIVO
        // (produtos com venda) — ver nota de divergência 2 no docblock da classe.
        $groupStats = $byGroup->map(function (Collection $items) {
            $active = $items->reject(fn ($item) => $item->sem_venda);

            $xValues = $active->pluck('x_value');
            $yValues = $active->pluck('y_value');

            return [
                'x_values' => $xValues,
                'y_values' => $yValues,
                'x_threshold' => $this->threshold($xValues),
                'y_threshold' => $this->threshold($yValues),
                'x_range' => SalesStatistics::range($xValues),
                'y_range' => SalesStatistics::range($yValues),
            ];
        });

        return $combined->map(function ($item) use ($groupStats) {
            $stats = $groupStats->get($item->group_id);

            $xThreshold = (float) $stats['x_threshold'];
            $yThreshold = (float) $stats['y_threshold'];

            $quadrant = $item->sem_venda
                ? 'baixo_baixo'
                : $this->classifyQuadrant((float) $item->x_value, (float) $item->y_value, $xThreshold, $yThreshold);

            // Produto sem venda não tem posição relativa ao sortimento ativo.
            $xPercentil = $item->sem_venda ? 0.0 : SalesStatistics::percentileRank((float) $item->x_value, $stats['x_values']);
            $yPercentil = $item->sem_venda ? 0.0 : SalesStatistics::percentileRank((float) $item->y_value, $stats['y_values']);

            $isBorderline = ! $item->sem_venda && (
                $this->isNearThreshold((float) $item->x_value, $xThreshold, (float) $stats['x_range'])
                || $this->isNearThreshold((float) $item->y_value, $yThreshold, (float) $stats['y_range'])
            );

            return [
                'product_id' => $item->product_id,
                'group_id' => $item->group_id,
                'quadrant' => $quadrant,
                'sem_venda' => (bool) $item->sem_venda,
                'x_axis' => $this->xAxis,
                'y_axis' => $this->yAxis,
                'x_value' => round((float) $item->x_value, 4),
                'y_value' => round((float) $item->y_value, 4),
                'x_threshold' => round($xThreshold, 4),
                'y_threshold' => round($yThreshold, 4),
                'x_percentil' => round($xPercentil, 2),
                'y_percentil' => round($yPercentil, 2),
                'is_borderline' => $isBorderline,
                'alerta_margem_negativa' => $this->hasNegativeMargin((float) $item->x_value, (float) $item->y_value),
            ];
        })->values();
    }

    /**
     * Cruza os quadrantes com o espaço ocupado na gôndola (GondolaSpaceService) e
     * deriva a ação de planograma. Etapa pura, não consulta o banco.
     *
     * É este cruzamento — e não o quadrante sozinho — que produz a recomendação:
     * saber que um produto vale muito só é acionável quando se sabe quanto de gôndola
     * ele custa. O corte de "muito/pouco espaço" é a MEDIANA do share da gôndola,
     * pela mesma razão de a mediana cortar os eixos: share de gôndola também tem
     * cauda longa (poucos produtos dominam o linear).
     *
     *   alto_alto   + espaço abaixo da mediana → 'aumentar'  (vende bem, está espremido)
     *   baixo_baixo + espaço acima da mediana  → 'reduzir'   (fraco ocupando gôndola nobre)
     *   demais casos                            → 'manter'
     *   produto sem largura cadastrada          → null (não há base para opinar)
     *
     * @param  Collection  $results  Saída de classifyQuadrants (arrays)
     * @param  array<string, array{facings: int, espaco_linear_cm: float, share_gondola: float, sem_dimensao: bool}>  $space
     */
    public function withSpace(Collection $results, array $space): Collection
    {
        $default = [
            'facings' => 0,
            'espaco_linear_cm' => 0.0,
            'share_gondola' => 0.0,
            'sem_dimensao' => true,
        ];

        // Espaço de cada linha: o do produto, ou a soma dos produtos da categoria
        // (modo agregado, sinalizado por member_product_ids).
        $resolved = $results->map(function (array $item) use ($space, $default) {
            $rowSpace = isset($item['member_product_ids'])
                ? $this->aggregateSpace($item['member_product_ids'], $space)
                : ($space[$item['product_id']] ?? $default);

            return ['item' => $item, 'space' => $rowSpace];
        });

        // Mediana do share entre as LINHAS com dimensão cadastrada: por categoria, é a
        // mediana das categorias; por produto, dos produtos (equivalente ao anterior).
        // Quem não tem largura entra com share 0 e puxaria o corte para baixo à toa.
        $shares = $resolved
            ->reject(fn (array $pair) => $pair['space']['sem_dimensao'])
            ->map(fn (array $pair) => $pair['space']['share_gondola']);

        $shareThreshold = (float) (SalesStatistics::median($shares) ?? 0.0);

        return $resolved->map(function (array $pair) use ($shareThreshold) {
            $item = $pair['item'];
            $rowSpace = $pair['space'];

            // No modo agregado (exibir por categoria/nível) a linha não tem EAN: sem os
            // produtos membros o front não teria como marcar cada produto na gôndola com
            // o selo da SUA categoria. Por isso member_product_ids segue no resultado.

            return array_merge($item, $rowSpace, [
                'share_threshold_gondola' => round($shareThreshold, 4),
                'acao_espaco' => $this->spaceAction(
                    $item['quadrant'],
                    (float) $rowSpace['share_gondola'],
                    (bool) $rowSpace['sem_dimensao'],
                    $shareThreshold,
                ),
            ]);
        });
    }

    /**
     * Soma o espaço de gôndola de um conjunto de produtos (uma categoria agregada).
     * A categoria só fica "sem dimensão" se NENHUM produto membro tiver largura.
     *
     * @param  array<int, string>  $productIds
     * @param  array<string, array{facings: int, espaco_linear_cm: float, share_gondola: float, sem_dimensao: bool}>  $space
     * @return array{facings: int, espaco_linear_cm: float, share_gondola: float, sem_dimensao: bool}
     */
    private function aggregateSpace(array $productIds, array $space): array
    {
        $facings = 0;
        $linear = 0.0;
        $share = 0.0;
        $comDimensao = false;

        foreach ($productIds as $productId) {
            $productSpace = $space[$productId] ?? null;

            if ($productSpace === null) {
                continue;
            }

            $facings += (int) $productSpace['facings'];
            $linear += (float) $productSpace['espaco_linear_cm'];

            if (! $productSpace['sem_dimensao']) {
                $share += (float) $productSpace['share_gondola'];
                $comDimensao = true;
            }
        }

        return [
            'facings' => $facings,
            'espaco_linear_cm' => $linear,
            'share_gondola' => $share,
            'sem_dimensao' => ! $comDimensao,
        ];
    }

    /**
     * Retorna os IDs dos produtos fisicamente alocados em uma gôndola.
     * Usa joins explícitos porque o modelo Layer não define relacionamentos para cima na hierarquia.
     */
    public function getProductIdsByGondola(string $gondolaId): array
    {
        return Layer::query()
            ->join('segments', 'segments.id', '=', 'layers.segment_id')
            ->join('shelves', 'shelves.id', '=', 'segments.shelf_id')
            ->join('sections', 'sections.id', '=', 'shelves.section_id')
            ->where('sections.gondola_id', $gondolaId)
            ->whereNotNull('layers.product_id')
            ->whereNull('layers.deleted_at')
            ->whereNull('segments.deleted_at')
            ->whereNull('shelves.deleted_at')
            ->whereNull('sections.deleted_at')
            ->distinct()
            ->pluck('layers.product_id')
            ->toArray();
    }

    /**
     * Resolve o grupo de comparação de cada produto: a categoria ancestral no nível
     * definido por classify_by.
     *
     * Carrega TODAS as categorias de uma vez e sobe a hierarquia em memória, com memo
     * por categoria. A alternativa óbvia — chamar Category::getFullHierarchy() por
     * produto — dispara uma query por nível por produto (uma gôndola de 200 SKUs faria
     * até mil queries).
     *
     * Hierarquia mais curta que o nível pedido cai no nível mais profundo disponível
     * (mesma tolerância da ABC): um departamento sem subdepartamentos não pode ser
     * comparado a um nível que não existe.
     *
     * @param  Collection<string, Product>  $products
     * @param  string  $level  Nível da hierarquia a resolver (chave de HIERARCHY_LEVELS)
     * @return array{0: array<string, string|null>, 1: array<string, string>} [product_id => group_id], [group_id => nome]
     */
    private function resolveGroups(Collection $products, string $level): array
    {
        $levelIndex = self::HIERARCHY_LEVELS[$level];

        $categories = Category::query()->get(['id', 'name', 'category_id'])->keyBy('id');

        $memo = [];
        $groupIdByProduct = [];
        $groupNames = [];

        foreach ($products as $product) {
            $categoryId = $product->category_id;

            if ($categoryId === null) {
                $groupIdByProduct[$product->id] = null;

                continue;
            }

            if (! array_key_exists($categoryId, $memo)) {
                // Sobe da folha até a raiz montando a cadeia raiz→folha
                $chain = [];
                $current = $categories->get($categoryId);
                $guard = 32;

                while ($current !== null && $guard-- > 0) {
                    array_unshift($chain, $current->id);
                    $current = $current->category_id !== null
                        ? $categories->get($current->category_id)
                        : null;
                }

                $memo[$categoryId] = $chain[$levelIndex] ?? (end($chain) ?: null);
            }

            $groupId = $memo[$categoryId];
            $groupIdByProduct[$product->id] = $groupId;

            if ($groupId !== null && ! isset($groupNames[$groupId])) {
                $groupNames[$groupId] = $categories->get($groupId)?->name ?? '';
            }
        }

        return [$groupIdByProduct, $groupNames];
    }

    /**
     * Aplica o método de corte configurado. Grupo sem nenhum produto com venda → 0.
     *
     * @param  Collection<int, float>  $values
     */
    private function threshold(Collection $values): float
    {
        $result = $this->thresholdMethod === self::THRESHOLD_MEAN
            ? SalesStatistics::mean($values)
            : SalesStatistics::median($values);

        return (float) ($result ?? 0.0);
    }

    /**
     * Classifica o produto em um dos quatro quadrantes.
     * Comparação inclusiva (>=) nos dois eixos, consistente com a Análise de Papel.
     */
    private function classifyQuadrant(float $x, float $y, float $xThreshold, float $yThreshold): string
    {
        $isHighX = $x >= $xThreshold;
        $isHighY = $y >= $yThreshold;

        return match (true) {
            $isHighX && $isHighY => 'alto_alto',
            $isHighX && ! $isHighY => 'forte_x',
            ! $isHighX && $isHighY => 'forte_y',
            default => 'baixo_baixo',
        };
    }

    /**
     * Ação de planograma derivada do cruzamento quadrante × espaço ocupado.
     * Só os dois extremos geram ação; o resto é 'manter' (ver nota em withSpace).
     */
    private function spaceAction(string $quadrant, float $shareGondola, bool $semDimensao, float $shareThreshold): ?string
    {
        if ($semDimensao) {
            return null;
        }

        return match (true) {
            $quadrant === 'alto_alto' && $shareGondola < $shareThreshold => 'aumentar',
            $quadrant === 'baixo_baixo' && $shareGondola > $shareThreshold => 'reduzir',
            default => 'manter',
        };
    }

    /**
     * Item "em cima da linha": a menos de BORDERLINE_RATIO da amplitude do grupo do
     * limiar. Grupo sem dispersão (amplitude 0) não tem borda — todos são idênticos.
     */
    private function isNearThreshold(float $value, float $threshold, float $range): bool
    {
        if ($range <= 0.0) {
            return false;
        }

        return abs($value - $threshold) <= ($range * self::BORDERLINE_RATIO);
    }

    /**
     * Sinaliza produto vendido com margem de contribuição negativa (prejuízo), quando
     * a margem é um dos eixos escolhidos. Um item assim pode cair em 'alto_alto' pelo
     * volume e passar despercebido.
     */
    private function hasNegativeMargin(float $xValue, float $yValue): bool
    {
        return ($this->xAxis === 'margem' && $xValue < 0)
            || ($this->yAxis === 'margem' && $yValue < 0);
    }

    /**
     * Busca vendas agregadas por produto no período, somando as colunas dos dois eixos.
     */
    private function getSalesData(array $codigosErp, array $productIds, string $tableType, array $filters): Collection
    {
        $query = match ($tableType) {
            'monthly_summaries' => $this->getMonthlySummariesQuery($codigosErp, $productIds, $filters),
            default => $this->getSalesQuery($codigosErp, $productIds, $filters),
        };

        return $query->get()->toBase();
    }

    /**
     * Query para a tabela de vendas transacionais (sales), período por data diária.
     */
    private function getSalesQuery(array $codigosErp, array $productIds, array $filters): Builder
    {
        $agg = ProductSalesAggregateQuery::for('sales');

        $query = $this->selectAxes($agg, $codigosErp, $productIds, $filters);

        $agg->applyPeriod($query, $filters['date_from'] ?? null, $filters['date_to'] ?? null);

        return $query;
    }

    /**
     * Query para a tabela de sumários mensais, período por start_month/end_month.
     *
     * Usa start_month/end_month (convenção do Paper) e NÃO month_from/month_to: essas
     * chaves nunca chegam do controller nem do frontend, e é por isso que a ABC e o
     * Estoque-Alvo ignoram o período silenciosamente no modo mensal.
     */
    private function getMonthlySummariesQuery(array $codigosErp, array $productIds, array $filters): Builder
    {
        $agg = ProductSalesAggregateQuery::for('monthly_summaries');

        $query = $this->selectAxes($agg, $codigosErp, $productIds, $filters);

        $from = isset($filters['start_month']) ? $filters['start_month'].'-01' : null;
        $to = isset($filters['end_month'])
            ? Carbon::createFromFormat('Y-m', $filters['end_month'])->endOfMonth()->format('Y-m-d')
            : null;

        $agg->applyPeriod($query, $from, $to);

        return $query;
    }

    /**
     * Monta a query agrupada por produto somando as colunas dos eixos configurados.
     * Os aliases são fixos (x_value/y_value) para o resto do service não depender de
     * qual métrica foi escolhida.
     */
    private function selectAxes(
        ProductSalesAggregateQuery $agg,
        array $codigosErp,
        array $productIds,
        array $filters
    ): Builder {
        return $agg->groupedByProduct($codigosErp, $productIds, $filters)
            ->addSelect([
                $agg->sum(self::AXIS_COLUMNS[$this->xAxis], 'x_value'),
                $agg->sum(self::AXIS_COLUMNS[$this->yAxis], 'y_value'),
            ]);
    }
}
