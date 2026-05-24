<?php

namespace App\Services\AutoPlanogram;

use App\Enums\PlacementFailureReason;
use App\Models\PlanogramSubtemplate;
use App\Models\PlanogramTemplateSlot;
use App\Models\Scopes\TenantScope;
use App\Services\AutoPlanogram\DTO\PlacementResult;
use App\Services\AutoPlanogram\DTO\PlanogramInput;
use App\Services\AutoPlanogram\DTO\PlanogramOutput;
use App\Services\AutoPlanogram\Placement\PlanogramWriterInterface;
use App\Services\AutoPlanogram\Placement\RejectedProductsWriter;
use App\Services\AutoPlanogram\Placement\TemplatePlacementEngine;
use App\Services\AutoPlanogram\Scoring\ProductScorerInterface;
use App\Services\AutoPlanogram\Synthesis\AutoTemplateSynthesisOrchestrator;
use App\Services\AutoPlanogram\Template\SlotSuggestionGenerator;
use App\Services\AutoPlanogram\Validation\PlanogramValidator;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Section;
use Callcocam\LaravelRaptorPlannerate\Services\Plannerate\ShelfStructureService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orquestrador do pipeline de geração de planogramas.
 *
 * Modo template (quando settings->templateId !== null):
 * 1. Score (scoreOrNeutral) — ordena pool por relevância; neutro se sem dados de venda
 * 2. TemplatePlacementEngine — distribui produtos pelos slots do subtemplate
 * 3. Validation — verifica integridade do resultado
 * 4. Write — persiste no banco em transação
 *
 * Modo automático (sem template):
 * 1. Score — pontua produtos por importância
 * 2. AutoTemplateSynthesisOrchestrator — sintetiza template a partir do mix
 * 3. TemplatePlacementEngine — distribui produtos pelos slots sintetizados
 * 4. Validation — verifica integridade do resultado
 * 5. Write — persiste no banco em transação
 */
final class AutoPlanogramService
{
    public function __construct(
        private readonly ProductScorerInterface $scorer,
        private readonly TemplatePlacementEngine $templatePlacement,
        private readonly PlanogramValidator $validator,
        private readonly PlanogramWriterInterface $writer,
        private readonly SlotSuggestionGenerator $suggestionGenerator,
        private readonly RejectedProductsWriter $rejectedProductsWriter,
        private readonly AutoTemplateSynthesisOrchestrator $synthesisOrchestrator,
        private readonly ShelfStructureService $shelfStructure,
    ) {}

    public function generate(PlanogramInput $input): PlanogramOutput
    {
        Log::info('AutoPlanogramService: iniciando geração', [
            'gondola_id' => $input->gondolaId,
            'planogram_id' => $input->planogramId,
            'products_count' => $input->products->count(),
            'strategy' => $input->settings->strategy,
            'mode' => $input->settings->usesTemplate() ? 'template' : 'automatic',
            'template_id' => $input->settings->templateId,
            'score_mode' => $input->settings->usesTemplate() ? 'optional' : 'required',
        ]);

        $scored = $this->scorer->scoreOrNeutral($input->products, $input->settings);

        $scoreType = $scored->first()?->metadata['score_type'] ?? 'composite';
        Log::info('AutoPlanogramService: scoring concluído', [
            'count' => $scored->count(),
            'score_type' => $scoreType,
            'has_sales' => $scoreType !== 'neutral',
        ]);

        if ($input->settings->usesTemplate()) {
            return $this->generateWithTemplate($input, $scored, $scoreType);
        }

        Log::info('AutoPlanogramService: modo automático — iniciando síntese de template', [
            'gondola_id' => $input->gondolaId,
            'category_id' => $input->settings->categoryId,
            'planogram_base_category_id' => $input->planogramCategoryId,
        ]);

        $planogramBaseCategoryId = $input->planogramCategoryId
            ?? $input->settings->categoryId
            ?? '';

        $subtemplate = $this->synthesisOrchestrator->orchestrate($input, $scored, $planogramBaseCategoryId);

        // Modo automático dirige a estrutura: o motor cria as prateleiras conforme o template
        // sintetizado, dentro do envelope físico das seções. Gôndolas que já têm prateleiras
        // (regeração) são preservadas.
        $this->ensureShelvesForSynthesizedTemplate($input->sections, $subtemplate);

        // Usa o num_modules real do subtemplate (dirigido pela demanda, não pela grade física).
        // Garante que o engine encontre o subtemplate via (template_id, num_modules) corretamente.
        $numModules = $subtemplate->num_modules;
        $updatedSettings = $input->settings->withTemplate(
            templateId: $subtemplate->template_id,
            numModules: $numModules,
            planogramId: $input->planogramId,
            products: $input->products,
        );

        $updatedInput = new PlanogramInput(
            planogramId: $input->planogramId,
            gondolaId: $input->gondolaId,
            tenantId: $input->tenantId,
            products: $input->products,
            sections: $input->sections,
            settings: $updatedSettings,
            planogramCategoryId: $input->planogramCategoryId,
        );

        Log::info('AutoPlanogramService: template sintetizado — delegando ao TemplatePlacementEngine', [
            'template_id' => $subtemplate->template_id,
            'subtemplate_id' => $subtemplate->getKey(),
            'num_modules' => $numModules,
        ]);

        return $this->generateWithTemplate($updatedInput, $scored, $scoreType);
    }

    /**
     * Reconstrói as prateleiras físicas das seções a partir do template sintetizado.
     *
     * Sempre apaga todas as prateleiras existentes antes de criar as novas (modo "partir do zero").
     * O número de prateleiras por módulo = max(MIN_SHELVES_PER_MODULE, shelf_orders distintos dos slots).
     * Seções sem slots no template ficam sem prateleiras (módulos não usados pelo template).
     *
     * @param  Collection<int, Section>  $sections
     */
    private function ensureShelvesForSynthesizedTemplate(Collection $sections, PlanogramSubtemplate $subtemplate): void
    {
        // shelf_orders distintos por módulo, na ordem dos slots do subtemplate
        $shelvesByModule = PlanogramTemplateSlot::withoutGlobalScope(TenantScope::class)
            ->where('subtemplate_id', $subtemplate->getKey())
            ->get(['module_number', 'shelf_order'])
            ->groupBy('module_number')
            ->map(fn (Collection $slots): int => $slots->pluck('shelf_order')->unique()->count());

        // Apagar todas as prateleiras existentes de todas as seções (geração parte do zero).
        // Segmentos e layers são apagados em cascata pelo banco ou pela PlanogramWriter.
        foreach ($sections as $section) {
            $section->shelves()->delete();
        }

        // Resetar relação em memória após deleção
        $sections->each(fn (Section $s) => $s->setRelation('shelves', collect()));

        foreach ($sections as $index => $section) {
            $moduleNumber = $index + 1;
            $numShelves = (int) ($shelvesByModule[$moduleNumber] ?? 0);

            if ($numShelves <= 0) {
                // Seção além do numModules do template: fica sem prateleiras (coluna vazia na gôndola).
                continue;
            }

            // Garante mínimo de MIN_SHELVES_PER_MODULE prateleiras por módulo ativo.
            // Isso evita que módulos parciais (ex.: 1 slot apenas) fiquem com poucas prateleiras.
            $numShelves = max(AutoTemplateSynthesisOrchestrator::MIN_SHELVES_PER_MODULE, $numShelves);

            $this->shelfStructure->createShelves($section, [
                'shelf_width' => $section->width,
            ], $numShelves);
        }

        // Recarrega a relação para o engine (resolveShelf) e o writer enxergarem as novas prateleiras
        $sections->each(fn ($section) => $section->load('shelves'));
    }

    private function generateWithTemplate(PlanogramInput $input, Collection $scored, string $scoreType): PlanogramOutput
    {
        $scoreOrder = $scored->pluck('product.id')->flip();
        $sortedProducts = $input->settings->products
            ->sortBy(fn ($p) => $scoreOrder->get($p->id, PHP_INT_MAX))
            ->values();

        $zoneMetricsMap = $scored->mapWithKeys(fn ($sp) => [
            $sp->product->id => [
                'giro' => (float) ($sp->metadata['raw_quantity'] ?? 0),
                'margem' => (float) ($sp->metadata['raw_margem'] ?? 0.0),
            ],
        ])->all();

        $productRules = $this->loadProductRules($input->settings->tenantId ?? '');
        $slotOverrides = $this->loadGondolaSlotOverrides($input->gondolaId);

        $settings = $input->settings
            ->withExtras($input->settings->tenantId, $input->settings->weights)
            ->withProducts($sortedProducts)
            ->withZoneMetrics($zoneMetricsMap)
            ->withProductRules(
                $productRules['mandatoryProductIds'],
                $productRules['blockedProductIds'],
                $productRules['blockedBrands'],
                $productRules['blockedSubcategoryIds'],
            )
            ->withSlotOverrides($slotOverrides);

        $result = $this->templatePlacement->place(
            collect(),
            $input->sections,
            $settings,
        );

        $allSegments = $this->renumberOrderings($result->placedSegments);
        $fullResult = new PlacementResult($allSegments, $result->rejectedProducts);

        $report = $this->validator->validate($allSegments, $input, $fullResult);

        // Denominador real: apenas produtos que chegaram a algum slot (colocados + rejeitados com produto)
        $reachableCount = $allSegments->count()
            + $fullResult->rejectedProducts->filter(fn ($r) => $r['product'] !== null)->count();
        $this->logCapacityAnalysis($input, $fullResult, $reachableCount);

        $suggestions = $this->suggestionGenerator->generate($result->slotAnalysis);

        $templateOutput = new PlanogramOutput(
            gondolaId: $input->gondolaId,
            placedSegments: $allSegments,
            rejectedProducts: $result->rejectedProducts,
            validationReport: $report,
            scoreType: $scoreType,
            slotAnalysis: $result->slotAnalysis,
            suggestions: $suggestions,
            modulesMismatch: $result->modulesMismatch,
            templateModules: $result->templateModules,
            gondolaModules: $result->gondolaModules,
            subtemplateId: $result->subtemplateId,
            explanationReport: $result->explanationReport,
        );

        DB::transaction(function () use ($input, $allSegments, $templateOutput): void {
            $this->writer->write($input->gondolaId, $input->sections, $allSegments);
            $this->rejectedProductsWriter->write($input->planogramId, $input->gondolaId, $input->tenantId, $templateOutput);
        });

        Log::info('AutoPlanogramService: geração com template concluída', [
            'gondola_id' => $input->gondolaId,
            'template_id' => $settings->templateId,
            'segments_placed' => $allSegments->count(),
            'validation_passed' => $report->passed,
            'score_type' => $scoreType,
            'sugestoes' => count($suggestions),
        ]);

        return $templateOutput;
    }

    /**
     * Carrega regras mandatory/blocked do banco do tenant para injetar no engine.
     *
     * @return array{mandatoryProductIds: array<string,true>, blockedProductIds: array<string,true>, blockedBrands: array<string,true>, blockedSubcategoryIds: array<string,true>}
     */
    private function loadProductRules(string $tenantId): array
    {
        if ($tenantId === '') {
            return [
                'mandatoryProductIds' => [],
                'blockedProductIds' => [],
                'blockedBrands' => [],
                'blockedSubcategoryIds' => [],
            ];
        }

        $rows = DB::connection('tenant')
            ->table('planogram_product_rules')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->get(['type', 'product_id', 'brand', 'subcategory_id']);

        $mandatoryProductIds = [];
        $blockedProductIds = [];
        $blockedBrands = [];
        $blockedSubcategoryIds = [];

        foreach ($rows as $row) {
            if ($row->type === 'mandatory' && $row->product_id !== null) {
                $mandatoryProductIds[$row->product_id] = true;
            }

            if ($row->type === 'blocked') {
                if ($row->product_id !== null) {
                    $blockedProductIds[$row->product_id] = true;
                }
                if ($row->brand !== null) {
                    $blockedBrands[$row->brand] = true;
                }
                if ($row->subcategory_id !== null) {
                    $blockedSubcategoryIds[$row->subcategory_id] = true;
                }
            }
        }

        return compact('mandatoryProductIds', 'blockedProductIds', 'blockedBrands', 'blockedSubcategoryIds');
    }

    /**
     * Carrega overrides de geração por categoria da gôndola, indexados por category_id.
     * Campos null são excluídos — apenas valores explícitos sobrepõem o template slot.
     *
     * @return array<string, array<string, mixed>>
     */
    private function loadGondolaSlotOverrides(string $gondolaId): array
    {
        if ($gondolaId === '') {
            return [];
        }

        $fields = [
            'category_id',
            'min_facings',
            'max_facings',
            'price_order',
            'size_order',
            'brand_exposure',
            'flavor_exposure',
            'space_fallback',
            'facing_expansion',
            'use_target_stock',
            'role_override',
            'max_share_per_sku',
            'max_share_per_brand',
            'max_share_per_subcategory',
        ];

        $rows = DB::connection('tenant')
            ->table('planogram_gondola_slot_overrides')
            ->where('gondola_id', $gondolaId)
            ->whereNull('deleted_at')
            ->get($fields);

        $result = [];

        foreach ($rows as $row) {
            if ($row->category_id === null) {
                continue;
            }

            $values = (array) $row;
            unset($values['category_id']);

            $result[$row->category_id] = array_filter($values, fn ($v) => $v !== null);
        }

        return $result;
    }

    /**
     * Renumera orderings dentro de cada shelf, ordenando por position.
     *
     * @param  Collection<int, DTO\PlacedSegment>  $segments
     * @return Collection<int, DTO\PlacedSegment>
     */
    private function renumberOrderings(Collection $segments): Collection
    {
        return $segments
            ->groupBy('shelfId')
            ->flatMap(function (Collection $shelfSegments): Collection {
                return $shelfSegments
                    ->sortBy('position')
                    ->values()
                    ->map(function (DTO\PlacedSegment $seg, int $i): DTO\PlacedSegment {
                        return new DTO\PlacedSegment(
                            sectionId: $seg->sectionId,
                            shelfId: $seg->shelfId,
                            ordering: $i,
                            position: $seg->position,
                            width: $seg->width,
                            distributedWidth: $seg->distributedWidth,
                            layers: $seg->layers,
                            isVerticalBlock: $seg->isVerticalBlock,
                        );
                    });
            })
            ->values();
    }

    private function logWidthQuality(PlanogramInput $input): void
    {
        $widths = $input->products->map(fn ($p) => (float) ($p->width ?? 0));

        Log::info('AutoPlanogramService: qualidade de dados de width', [
            'total_produtos' => $widths->count(),
            'sem_width' => $input->products->whereNull('width')->count(),
            'width_zero' => $widths->filter(fn ($w) => $w <= 0)->count(),
            'width_suspeito' => $widths->filter(fn ($w) => $w > 60)->count(),
            'width_valido' => $widths->filter(fn ($w) => $w > 0 && $w <= 60)->count(),
            'largura_media_cm' => round($widths->filter(fn ($w) => $w > 0 && $w <= 60)->avg() ?? 0, 2),
        ]);
    }

    private function logCapacityAnalysis(PlanogramInput $input, PlacementResult $result, ?int $reachableCount = null): void
    {
        // Modo template: denominar só pelos produtos que alcançaram algum slot; modo auto: pool completo
        $totalProducts = $reachableCount ?? $input->products->count();
        $placed = $result->placedSegments->count();

        // Produtos únicos definitivamente sem espaço: exclui os que foram rejeitados de um slot
        // mas colocados em outro da mesma categoria (contagem por eventos seria enganosa).
        $placedProductIds = $result->placedSegments
            ->flatMap(fn ($seg) => $seg->layers->map(fn ($l) => $l->productId))
            ->flip()
            ->all();
        $rejectedSpace = $result->rejectedProducts
            ->filter(fn ($r) => $r['reason'] === PlacementFailureReason::NoHorizontalSpace
                && $r['product'] !== null
                && ! isset($placedProductIds[$r['product']->id]))
            ->unique(fn ($r) => $r['product']->id)
            ->count();
        $rejectedHeight = $result->rejectedProducts
            ->filter(fn ($r) => $r['reason'] === PlacementFailureReason::HeightExceedsShelf)
            ->count();

        $mixExceedsGondola = $rejectedSpace > 0;

        Log::info('AutoPlanogramService: análise de capacidade', [
            'produtos_com_venda' => $totalProducts,
            'posicionados' => $placed,
            'rejeitados_sem_espaco' => $rejectedSpace,
            'rejeitados_altura' => $rejectedHeight,
            'mix_excede_gondola' => $mixExceedsGondola,
            'taxa_cobertura' => round($placed / max($totalProducts, 1), 3),
            'recomendacao' => $mixExceedsGondola
                ? "Mix excede capacidade da gôndola. Considere ampliar a gôndola ou reduzir o mix em {$rejectedSpace} produto(s)."
                : 'Mix dentro da capacidade.',
        ]);
    }
}
