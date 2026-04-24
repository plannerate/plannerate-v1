<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant;

use Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\GondolaAnalysis;
use Callcocam\LaravelRaptorPlannerate\Services\Plannerate\AbcAnalysisService;
use Callcocam\LaravelRaptorPlannerate\Services\Plannerate\TargetStockService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GondolaAnalysisController
{
    public function __construct(
        private AbcAnalysisService $abcService,
        private TargetStockService $targetStockService
    ) {}

    /**
     * Calcula análise ABC para os produtos de uma gôndola
     *
     * Busca todos os produtos através da hierarquia:
     * layer -> segment -> shelf -> section -> gondola -> planogram
     */
    public function calculateAbc(Request $request, string $gondolaId): Response
    {
        $gondola = $this->getGondolaWithRelations($gondolaId);

        if (! $gondola->planogram) {
            return Inertia::render('tenant/analysis/abc/Index', [
                'error' => 'Gôndola não possui planograma associado',
                'initialData' => [
                    'gondola' => $this->formatGondolaData($gondola),
                ],
            ]);
        }

        $eans = $this->extractEansFromGondola($gondola);

        if (empty($eans)) {
            return Inertia::render('tenant/analysis/abc/Index', [
                'error' => 'Nenhum produto encontrado na gôndola',
                'initialData' => [
                    'gondola' => $this->formatGondolaData($gondola),
                ],
            ]);
        }

        $filters = $this->prepareFilters($request, $gondola);
        $weights = $this->getWeightsFromRequest($request);
        $cuts = $this->getCutsFromRequest($request);

        $this->abcService->setWeights($weights['peso_qtde'], $weights['peso_valor'], $weights['peso_margem'])
            ->setCuts($cuts['corte_a'], $cuts['corte_b']);

        $tableType = $request->input('table_type', 'sales');
        $results = $this->abcService->analyzeByEans($eans, $tableType, $filters);
        $formattedResults = $this->formatAbcResults($results);

        $category = $this->getCategoryData($gondola);

        // Salvar análise no banco de dados
        $this->saveAbcAnalysis($gondola, $formattedResults, $filters, $weights, $cuts);

        return Inertia::render('tenant/analysis/abc/Index', [
            'initialData' => [
                'results' => $formattedResults->values()->all(),
                'category' => $category['category'],
                'category_id' => $category['category_id'],
                'eans' => array_values($eans),
                'gondola' => $this->formatGondolaData($gondola),
                'planogram' => $this->formatPlanogramData($gondola->planogram),
                'filters' => [
                    'table_type' => $tableType,
                    'client_id' => $filters['client_id'],
                    'date_from' => $filters['date_from'] ?? null,
                    'date_to' => $filters['date_to'] ?? null,
                ],
                'weights' => $weights,
                'cuts' => $cuts,
            ],
        ]);
    }

    /**
     * Calcula estoque alvo para os produtos de uma gôndola
     *
     * Depende da análise ABC para funcionar corretamente
     */
    public function calculateTargetStock(Request $request, string $gondolaId): Response
    {
        $gondola = $this->getGondolaWithRelations($gondolaId);

        if (! $gondola->planogram) {
            return Inertia::render('tenant/analysis/target-stock/Index', [
                'error' => 'Gôndola não possui planograma associado',
                'initialData' => [
                    'gondola' => $this->formatGondolaData($gondola),
                ],
            ]);
        }

        $eans = $this->extractEansFromGondola($gondola);

        if (empty($eans)) {
            return Inertia::render('tenant/analysis/target-stock/Index', [
                'error' => 'Nenhum produto encontrado na gôndola',
                'initialData' => [
                    'gondola' => $this->formatGondolaData($gondola),
                ],
            ]);
        }

        $filters = $this->prepareFilters($request, $gondola);
        $weights = $this->getWeightsFromRequest($request);
        $cuts = $this->getCutsFromRequest($request);

        $this->abcService->setWeights($weights['peso_qtde'], $weights['peso_valor'], $weights['peso_margem'])
            ->setCuts($cuts['corte_a'], $cuts['corte_b']);

        $tableType = $request->input('table_type', 'sales');
        $abcResults = $this->abcService->analyzeByEans($eans, $tableType, $filters);

        if ($abcResults->isEmpty()) {
            return Inertia::render('tenant/analysis/target-stock/Index', [
                'error' => 'Nenhum resultado ABC encontrado para calcular estoque alvo',
                'initialData' => [
                    'gondola' => $this->formatGondolaData($gondola),
                ],
            ]);
        }

        $parameters = $this->getTargetStockParametersFromRequest($request);
        $this->targetStockService
            ->setPeriodType($parameters['period_type'])
            ->setServiceLevels($parameters['nivel_servico_a'], $parameters['nivel_servico_b'], $parameters['nivel_servico_c'])
            ->setCoverageDays($parameters['cobertura_dias_a'], $parameters['cobertura_dias_b'], $parameters['cobertura_dias_c']);

        $currentStock = [];
        $targetStockResults = $this->targetStockService->calculateByAbcResults(
            $abcResults->toArray(),
            $tableType,
            $filters,
            $currentStock
        );

        $formattedResults = $this->formatTargetStockResults($targetStockResults);

        // Salvar análise no banco de dados
        $this->saveTargetStockAnalysis($gondola, $formattedResults, $filters, $parameters);

        return Inertia::render('tenant/analysis/target-stock/Index', [
            'initialData' => [
                'results' => $formattedResults->values()->all(),
                'gondola' => $this->formatGondolaData($gondola),
                'planogram' => $this->formatPlanogramData($gondola->planogram),
                'filters' => [
                    'table_type' => $tableType,
                    'client_id' => $filters['client_id'],
                    'date_from' => $filters['date_from'] ?? null,
                    'date_to' => $filters['date_to'] ?? null,
                ],
                'parameters' => $parameters,
            ],
        ]);
    }

    /**
     * API: Calcula análise ABC para os produtos de uma gôndola (retorna JSON)
     *
     * Usado pela modal de performance no editor
     */
    public function calculateAbcApi(Request $request, string $gondolaId)
    {
        $gondola = $this->getGondolaWithRelations($gondolaId);

        if (! $gondola->planogram) {
            return redirect()->back()->with('error', 'Gôndola não possui planograma associado');
        }

        $eans = $this->extractEansFromGondola($gondola);

        if (empty($eans)) {
            return redirect()->back()->with('error', 'Nenhum produto encontrado na gôndola');
        }

        $filters = $this->prepareFilters($request, $gondola);
        $weights = $this->getWeightsFromRequest($request);
        $cuts = $this->getCutsFromRequest($request);

        $this->abcService->setWeights($weights['peso_qtde'], $weights['peso_valor'], $weights['peso_margem'])
            ->setCuts($cuts['corte_a'], $cuts['corte_b']);

        $tableType = $request->input('table_type', 'sales');
        $results = $this->abcService->analyzeByEans($eans, $tableType, $filters);
        $formattedResults = $this->formatAbcResults($results);

        $this->saveAbcAnalysis($gondola, $formattedResults, $filters, $weights, $cuts);

        return redirect()->back();
    }

    /**
     * API: Calcula estoque alvo para os produtos de uma gôndola (retorna JSON)
     *
     * Usado pela modal de performance no editor
     */
    public function calculateTargetStockApi(Request $request, string $gondolaId)
    {
        $gondola = $this->getGondolaWithRelations($gondolaId);

        if (! $gondola->planogram) {
            return redirect()->back()->with('error', 'Gôndola não possui planograma associado');
        }

        $eans = $this->extractEansFromGondola($gondola);

        if (empty($eans)) {
            return redirect()->back()->with('error', 'Nenhum produto encontrado na gôndola');
        }

        $filters = $this->prepareFilters($request, $gondola);
        $weights = $this->getWeightsFromRequest($request);
        $cuts = $this->getCutsFromRequest($request);

        $this->abcService->setWeights($weights['peso_qtde'], $weights['peso_valor'], $weights['peso_margem'])
            ->setCuts($cuts['corte_a'], $cuts['corte_b']);

        $tableType = $request->input('table_type', 'sales');
        $abcResults = $this->abcService->analyzeByEans($eans, $tableType, $filters);

        if ($abcResults->isEmpty()) {
            return redirect()->back()->with('error', 'Nenhum resultado ABC encontrado para calcular estoque alvo');
        }

        $parameters = $this->getTargetStockParametersFromRequest($request);
        $this->targetStockService
            ->setPeriodType($parameters['period_type'])
            ->setServiceLevels($parameters['nivel_servico_a'], $parameters['nivel_servico_b'], $parameters['nivel_servico_c'])
            ->setCoverageDays($parameters['cobertura_dias_a'], $parameters['cobertura_dias_b'], $parameters['cobertura_dias_c']);

        $currentStock = [];
        $targetStockResults = $this->targetStockService->calculateByAbcResults(
            $abcResults->toArray(),
            $tableType,
            $filters,
            $currentStock
        );

        $formattedResults = $this->formatTargetStockResults($targetStockResults);

        $this->saveTargetStockAnalysis($gondola, $formattedResults, $filters, $parameters);

        return redirect()->back();
    }

    /**
     * API: Remove análises de performance da gôndola (ABC e estoque alvo)
     */
    public function clearAnalysisApi(string $gondolaId)
    {
        $gondola = Gondola::query()->findOrFail($gondolaId);

        $deleted = GondolaAnalysis::query()
            ->where('gondola_id', $gondola->id)
            ->whereIn('type', ['abc', 'stock'])
            ->delete();

        return redirect()->back()->with('success', 'Análises removidas com sucesso.');
    }

    // ========================================================================
    // MÉTODOS PRIVADOS - Lógica reutilizável
    // ========================================================================

    /**
     * Busca gôndola com todas as relações necessárias
     */
    private function getGondolaWithRelations(string $gondolaId): Gondola
    {
        return Gondola::with([
            // Note: planogram.client removed - use $gondola->planogram->client instead
            'planogram.category',
            'sections.shelves.segments.layer.product',
        ])->findOrFail($gondolaId);
    }

    /**
     * Extrai EANs únicos dos produtos da gôndola
     *
     * @return array Array de EANs (strings)
     */
    private function extractEansFromGondola(Gondola $gondola): array
    {
        $eans = [];

        foreach ($gondola->sections as $section) {
            foreach ($section->shelves as $shelf) {
                foreach ($shelf->segments as $segment) {
                    if ($segment->layer && $segment->layer->product) {
                        $product = $segment->layer->product;
                        if ($product->ean) {
                            $eans[] = $product->ean;
                        }
                    }
                }
            }
        }

        // Remove duplicatas e converte para string
        return array_map('strval', array_unique($eans));
    }

    /**
     * Prepara filtros usando dados do planograma ou parâmetros da requisição
     */
    private function prepareFilters(Request $request, Gondola $gondola): array
    {
        $filters = [
            'client_id' => $gondola->planogram->client_id ?? config('app.current_client_id'),
        ];

        $tableType = $request->input('table_type', 'sales');

        // Para monthly_summaries, usa start_month e end_month
        if ($tableType === 'monthly_summaries') {
            if ($request->filled('start_month')) {
                // start_month vem no formato YYYY-MM do input type="month"
                $filters['month_from'] = Carbon::parse($request->input('start_month'))->startOfMonth()->format('Y-m-d');
            } elseif ($gondola->planogram->start_date) {
                $startDate = $gondola->planogram->start_date;
                $filters['month_from'] = Carbon::parse($startDate)->startOfMonth()->format('Y-m-d');
            }

            if ($request->filled('end_month')) {
                // end_month vem no formato YYYY-MM do input type="month"
                $filters['month_to'] = Carbon::parse($request->input('end_month'))->endOfMonth()->format('Y-m-d');
            } elseif ($gondola->planogram->end_date) {
                $endDate = $gondola->planogram->end_date;
                $filters['month_to'] = Carbon::parse($endDate)->endOfMonth()->format('Y-m-d');
            }
        } else {
            // Para sales, usa date_from e date_to
            if ($request->filled('date_from')) {
                $filters['date_from'] = $request->input('date_from');
            } elseif ($gondola->planogram->start_date) {
                $startDate = $gondola->planogram->start_date;
                $filters['date_from'] = is_string($startDate)
                    ? $startDate
                    : $startDate->format('Y-m-d');
            }

            if ($request->filled('date_to')) {
                $filters['date_to'] = $request->input('date_to');
            } elseif ($gondola->planogram->end_date) {
                $endDate = $gondola->planogram->end_date;
                $filters['date_to'] = is_string($endDate)
                    ? $endDate
                    : $endDate->format('Y-m-d');
            }
        }

        return $filters;
    }

    /**
     * Obtém pesos da requisição ou usa valores padrão
     */
    private function getWeightsFromRequest(Request $request): array
    {
        return [
            'peso_qtde' => (float) $request->input('peso_qtde', 0.3),
            'peso_valor' => (float) $request->input('peso_valor', 0.3),
            'peso_margem' => (float) $request->input('peso_margem', 0.4),
        ];
    }

    /**
     * Obtém cortes da requisição ou usa valores padrão
     */
    private function getCutsFromRequest(Request $request): array
    {
        return [
            'corte_a' => (float) $request->input('corte_a', 0.80),
            'corte_b' => (float) $request->input('corte_b', 0.85),
        ];
    }

    /**
     * Obtém parâmetros de estoque alvo da requisição ou usa valores padrão
     */
    private function getTargetStockParametersFromRequest(Request $request): array
    {
        return [
            'period_type' => $request->input('period_type', 'daily'),
            'nivel_servico_a' => (float) $request->input('nivel_servico_a', 0.7),
            'nivel_servico_b' => (float) $request->input('nivel_servico_b', 0.8),
            'nivel_servico_c' => (float) $request->input('nivel_servico_c', 0.9),
            'cobertura_dias_a' => (int) $request->input('cobertura_dias_a', 2),
            'cobertura_dias_b' => (int) $request->input('cobertura_dias_b', 5),
            'cobertura_dias_c' => (int) $request->input('cobertura_dias_c', 7),
        ];
    }

    /**
     * Formata resultados da análise ABC
     */
    private function formatAbcResults($results)
    {
        return $results->map(function ($item) {
            return [
                'product_id' => $item['product_id'],
                'product_name' => $item['product_name'],
                'ean' => $item['ean'],
                'image_url' => $item['image_url'] ?? null,
                'category_id' => $item['category_id'],
                'category_name' => $item['category_name'],
                'qtde' => (float) $item['qtde'],
                'valor' => (float) $item['valor'],
                'margem' => (float) $item['margem'],
                'media_ponderada' => (float) $item['media_ponderada'],
                'percentual_individual' => (float) $item['percentual_individual'],
                'percentual_acumulado' => (float) $item['percentual_acumulado'],
                'classificacao' => $item['classificacao'],
                'ranking' => (int) $item['ranking'],
                'class_rank' => $item['class_rank'],
                'retirar_do_mix' => (bool) $item['retirar_do_mix'],
                'status' => $item['status'],
            ];
        });
    }

    /**
     * Formata resultados de estoque alvo
     */
    private function formatTargetStockResults($results)
    {
        return $results->map(function ($item) {
            return [
                'product_id' => $item['product_id'],
                'product_name' => $item['product_name'],
                'ean' => $item['ean'],
                'classificacao' => $item['classificacao'],
                'demanda_media' => (float) $item['demanda_media'],
                'desvio_padrao' => (float) $item['desvio_padrao'],
                'variabilidade' => (float) $item['variabilidade'],
                'cobertura_dias' => (int) $item['cobertura_dias'],
                'nivel_servico' => (float) $item['nivel_servico'],
                'z_score' => (float) $item['z_score'],
                'estoque_seguranca' => (int) $item['estoque_seguranca'],
                'estoque_minimo' => (int) $item['estoque_minimo'],
                'estoque_alvo' => (int) $item['estoque_alvo'],
                'estoque_atual' => (int) $item['estoque_atual'],
                'permite_frentes' => $item['permite_frentes'],
                'alerta_variabilidade' => (bool) $item['alerta_variabilidade'],
            ];
        });
    }

    /**
     * Formata dados da gôndola para resposta
     */
    private function formatGondolaData(Gondola $gondola): array
    {
        return [
            'id' => $gondola->id,
            'name' => $gondola->name,
            'slug' => $gondola->slug,
        ];
    }

    /**
     * Formata dados do planograma para resposta
     */
    private function formatPlanogramData($planogram): array
    {
        return [
            'id' => $planogram->id,
            'name' => $planogram->name,
            'client_id' => $planogram->client_id,
            'start_date' => $planogram->start_date ?? null,
            'end_date' => $planogram->end_date ?? null,
            'start_month' => $planogram->getStartMonthInput(),
            'end_month' => $planogram->getEndMonthInput(),
        ];
    }

    /**
     * Obtém dados da categoria do planograma (se houver)
     */
    private function getCategoryData(Gondola $gondola): array
    {
        $category = null;
        $categoryId = null;

        if ($gondola->planogram->category_id && $gondola->planogram->category) {
            $category = [
                'id' => $gondola->planogram->category->id,
                'name' => $gondola->planogram->category->name,
            ];
            $categoryId = $gondola->planogram->category->id;
        }

        return [
            'category' => $category,
            'category_id' => $categoryId,
        ];
    }

    /**
     * Salva análise ABC no banco de dados
     */
    private function saveAbcAnalysis(
        Gondola $gondola,
        $formattedResults,
        array $filters,
        array $weights,
        array $cuts
    ): void {
        // Remover análises antigas do tipo ABC para esta gôndola
        GondolaAnalysis::ofType('abc')->where('gondola_id', $gondola->id)->delete();

        // Preparar dados para armazenar
        $resultsArray = $formattedResults->toArray();

        $summary = [
            'total_products' => count($resultsArray),
            'class_a_count' => collect($resultsArray)->where('classificacao', 'A')->count(),
            'class_b_count' => collect($resultsArray)->where('classificacao', 'B')->count(),
            'class_c_count' => collect($resultsArray)->where('classificacao', 'C')->count(),
            'filters' => $filters,
            'weights' => $weights,
            'cuts' => $cuts,
        ];

        // Salvar análise
        GondolaAnalysis::create([
            'gondola_id' => $gondola->id,
            'type' => 'abc',
            'data' => [
                'results' => $resultsArray,
                'filters' => $filters,
                'weights' => $weights,
                'cuts' => $cuts,
            ],
            'summary' => $summary,
            'analyzed_at' => now(),
        ]);
    }

    /**
     * Salva análise de Target Stock no banco de dados
     */
    private function saveTargetStockAnalysis(
        Gondola $gondola,
        $formattedResults,
        array $filters,
        array $parameters
    ): void {
        // Remover análises antigas do tipo stock para esta gôndola
        GondolaAnalysis::ofType('stock')->where('gondola_id', $gondola->id)->delete();

        // Preparar dados para armazenar
        $resultsArray = $formattedResults->toArray();

        $summary = [
            'total_products' => count($resultsArray),
            'total_target_stock' => collect($resultsArray)->sum('estoque_alvo'),
            'total_current_stock' => collect($resultsArray)->sum('estoque_atual'),
            'products_above_target' => collect($resultsArray)->where('estoque_atual', '>', 0)
                ->count(function (array $item) {
                    return $item['estoque_atual'] > $item['estoque_alvo'];
                }),
            'products_below_target' => collect($resultsArray)->where('estoque_atual', '<', DB::raw('estoque_alvo'))
                ->count(function (array $item) {
                    return $item['estoque_atual'] < $item['estoque_alvo'];
                }),
            'filters' => $filters,
            'parameters' => $parameters,
        ];

        // Salvar análise
        GondolaAnalysis::create([
            'gondola_id' => $gondola->id,
            'type' => 'stock',
            'data' => [
                'results' => $resultsArray,
                'filters' => $filters,
                'parameters' => $parameters,
            ],
            'summary' => $summary,
            'analyzed_at' => now(),
        ]);
    }
}
