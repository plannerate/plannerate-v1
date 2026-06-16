<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement;

use App\Models\Category;
use App\Models\Planogram;
use App\Models\Scopes\TenantScope;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\OrderedBlock;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacedLayer;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacedSegment;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacementResult;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacementSettings;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductOrderingService;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductSizeResolver;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductWidthResolver;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ShelfZoneResolver;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Synthesis\SlotPlanBuilder;
use Callcocam\LaravelRaptorPlannerate\Enums\FacingExpansion;
use Callcocam\LaravelRaptorPlannerate\Enums\FlowDirection;
use Callcocam\LaravelRaptorPlannerate\Enums\LayoutOrientation;
use Callcocam\LaravelRaptorPlannerate\Enums\PlacementFailureReason;
use Callcocam\LaravelRaptorPlannerate\Enums\SpaceFallback;
use Callcocam\LaravelRaptorPlannerate\Enums\ZonePriority;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramSubtemplate;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplateSlot;
use Callcocam\LaravelRaptorPlannerate\Models\Section;
use Callcocam\LaravelRaptorPlannerate\Models\Shelf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class TemplatePlacementEngine implements PlacementEngineInterface
{
    /** @var array<string, list<string>> Cache de descendentes por category_id dentro de uma geração */
    private array $descendantsCache = [];

    /** @var array<string, true> Produtos já posicionados na geração atual — evita duplicatas entre slots da mesma categoria */
    private array $globalPlacedProductIds = [];

    /** @var array<string, string> Mapa ABC [product_id => 'A'|'B'|'C'] vindo de PlacementSettings */
    private array $abcClassMap = [];

    /** @var array<string, string> Mapa de papel estratégico [product_id => 'leader'|'anchor'|'rising'|'lagging'] vindo de PlacementSettings */
    private array $bcgMap = [];

    /** @var array<string, float> Mapa de estoque alvo [product_id => float] vindo de PlacementSettings */
    private array $targetStockMap = [];

    /** @var array<string, array{giro: float, margem: float}> Métricas por produto para ordenação por zona */
    private array $zoneMetricsMap = [];

    /** @var array<string, true> Produtos obrigatórios [product_id => true] */
    private array $mandatoryProductIds = [];

    /** @var array<string, true> Produtos bloqueados [product_id => true] */
    private array $blockedProductIds = [];

    /** @var array<string, true> Marcas bloqueadas [brand => true] */
    private array $blockedBrands = [];

    /** @var array<string, true> Subcategorias bloqueadas [category_id => true] */
    private array $blockedSubcategoryIds = [];

    /** Critério de priorização para zona quente (Eye + Hand) */
    private ZonePriority $hotZonePriority = ZonePriority::None;

    /** Critério de priorização para zona fria (High + Low) */
    private ZonePriority $coldZonePriority = ZonePriority::None;

    /** Sentido de leitura do cliente — espelha posições físicas quando RightToLeft */
    private FlowDirection $flowDirection = FlowDirection::LeftToRight;

    /** Disposição dos produtos: horizontal (legado) ou vertical (blocagem por marca em colunas alinhadas) */
    private LayoutOrientation $layoutOrientation = LayoutOrientation::Horizontal;

    /** @var array<string, array<string, mixed>> Overrides por category_id desta gôndola [category_id => [campo => valor_raw]] */
    private array $gondolaSlotOverrides = [];

    public function __construct(
        private readonly ProductWidthResolver $widthResolver,
        private readonly ProductSizeResolver $sizeResolver,
        private readonly GreedyShelfPlacer $greedyPlacer,
        private readonly ProductOrderingService $ordering,
    ) {}

    /** @param Collection<int, Section> $sections */
    public function totalAvailableWidth(Collection $sections): float
    {
        return $this->greedyPlacer->totalAvailableWidth($sections);
    }

    /**
     * @param  Collection<int, OrderedBlock>  $orderedBlocks
     * @param  Collection<int, Section>  $sections
     * @param  array<string, float>  $reservedWidthPerShelf
     */
    public function place(
        Collection $orderedBlocks,
        Collection $sections,
        PlacementSettings $settings,
        array $reservedWidthPerShelf = [],
    ): PlacementResult {
        $this->globalPlacedProductIds = [];
        $this->abcClassMap = $settings->abcClassMap;
        $this->bcgMap = $settings->bcgMap;
        $this->targetStockMap = $settings->targetStockMap;
        $this->zoneMetricsMap = $settings->zoneMetricsMap;
        $this->mandatoryProductIds = $settings->mandatoryProductIds;
        $this->blockedProductIds = $settings->blockedProductIds;
        $this->blockedBrands = $settings->blockedBrands;
        $this->blockedSubcategoryIds = $settings->blockedSubcategoryIds;
        $this->gondolaSlotOverrides = $settings->gondolaSlotOverrides;

        $subtemplate = $this->resolveSubtemplate($settings);

        if ($subtemplate === null) {
            Log::warning('TemplatePlacementEngine: sem subtemplate para N módulos — usando greedy', [
                'num_modules' => $sections->count(),
                'template_id' => $settings->templateId,
            ]);

            return $this->greedyPlacer->place($orderedBlocks, $sections, $settings, $reservedWidthPerShelf);
        }

        $this->hotZonePriority = $subtemplate->hot_zone_priority ?? ZonePriority::None;
        $this->coldZonePriority = $subtemplate->cold_zone_priority ?? ZonePriority::None;
        $this->flowDirection = $subtemplate->flow_direction ?? FlowDirection::LeftToRight;
        $this->layoutOrientation = $subtemplate->layout_orientation ?? LayoutOrientation::Horizontal;

        $placed = collect();
        $rejected = collect();
        $groupingsSemProduto = 0;
        $slotAnalysis = [];
        $allPlacedExplanations = [];
        /** @var list<string> IDs de slots do template sem candidatos nesta geração */
        $emptySlotIds = [];
        /**
         * Largura já ocupada por prateleira (shelfId → float em cm).
         *
         * Permite que múltiplos slots (de categorias diferentes) compartilhem a mesma
         * prateleira física — micro-categorias são adensadas no espaço livre de categorias
         * já processadas na mesma prateleira.  O segundo slot começa após $occupiedPerShelf[id].
         */
        /** @var array<string, float> */
        $occupiedPerShelf = [];
        /**
         * Altura útil por prateleira (shelfId → vão livre em cm), cacheada por seção.
         * Usada para rejeitar com HeightExceedsShelf produtos mais altos que o vão.
         *
         * @var array<string, array<string, float>>
         */
        $clearancesBySection = [];

        $slots = $subtemplate->slots()
            ->withoutGlobalScope(TenantScope::class)
            ->with('category')
            ->orderBy('module_number')
            ->orderBy('shelf_order')
            ->orderBy('ordering')
            ->get();

        // Pré-scan: detecta posições (module_number, shelf_order) com múltiplos slots.
        // O primeiro slot de cada prateleira compartilhada precisa reservar espaço para o seguinte,
        // caso contrário o expandFacings consumirá toda a largura e a micro-categoria ficará sem espaço.
        $sharedShelfFirstSlotIds = []; // [slot_id => true]
        $positionSlotCounts = [];
        foreach ($slots as $s) {
            $posKey = $s->module_number.'_'.$s->shelf_order;
            $positionSlotCounts[$posKey] = ($positionSlotCounts[$posKey] ?? 0) + 1;
        }
        $seenSharedPositions = [];
        foreach ($slots as $s) {
            $posKey = $s->module_number.'_'.$s->shelf_order;
            if (($positionSlotCounts[$posKey] ?? 1) >= 2 && ! isset($seenSharedPositions[$posKey])) {
                $sharedShelfFirstSlotIds[$s->id] = true;
                $seenSharedPositions[$posKey] = true;
            }
        }

        // === BLOCAGEM VERTICAL (pré-passe) ===
        // Quando layout_orientation = vertical, grupos de slots do mesmo módulo+categoria
        // que ocupam prateleiras consecutivas são processados como colunas por marca
        // (mesma faixa de X em todas as prateleiras). Slots fora dos grupos elegíveis
        // (prateleiras compartilhadas, categorias de 1 prateleira) seguem o caminho
        // horizontal legado intocado.
        $verticalProcessedSlotIds = [];

        if ($this->layoutOrientation === LayoutOrientation::Vertical) {
            $verticalGroups = $this->buildVerticalGroups($slots, $positionSlotCounts);

            if ($verticalGroups === []) {
                // Sem grupo elegível a geração inteira sai horizontal — avisar em vez de
                // silenciar, pois o usuário pediu vertical e não verá coluna nenhuma
                Log::warning('TemplatePlacementEngine: blocagem vertical solicitada, mas nenhum grupo elegível', [
                    'motivo' => 'nenhuma categoria ocupa 2+ prateleiras consecutivas no mesmo módulo',
                    'dica' => 'sob compressão cada categoria tende a receber 1 prateleira — reduza o escopo de categorias ou use uma gôndola maior',
                    'slots' => $slots->count(),
                ]);
            }

            foreach ($verticalGroups as $group) {
                foreach ($group as $groupSlot) {
                    $verticalProcessedSlotIds[$groupSlot->id] = true;
                }

                $groupResult = $this->placeVerticalGroup($group, $sections, $settings, $clearancesBySection);

                $placed = $placed->merge($groupResult['placed']);
                $rejected = $rejected->merge($groupResult['rejected']);
                $slotAnalysis = array_merge($slotAnalysis, $groupResult['slot_analysis']);
                $allPlacedExplanations = array_merge($allPlacedExplanations, $groupResult['placed_explanations']);
                $emptySlotIds = array_merge($emptySlotIds, $groupResult['empty_slot_ids']);
                $groupingsSemProduto += count($groupResult['empty_slot_ids']);

                foreach ($groupResult['occupied_per_shelf'] as $vShelfId => $vWidth) {
                    $occupiedPerShelf[$vShelfId] = ($occupiedPerShelf[$vShelfId] ?? 0.0) + $vWidth;
                }
            }
        }

        foreach ($slots as $slot) {
            // Slots já processados pela blocagem vertical não passam pelo caminho horizontal
            if (isset($verticalProcessedSlotIds[$slot->id])) {
                continue;
            }

            $this->applySlotOverride($slot);

            $section = $this->resolveSection($sections, $slot->module_number);
            $shelf = $section ? $this->resolveShelf($section, $slot->shelf_order) : null;

            if ($section === null || $shelf === null) {
                $rejected->push([
                    'product' => null,
                    'reason' => PlacementFailureReason::NoShelfAtLevel,
                    'slot_id' => $slot->id,
                ]);

                continue;
            }

            $allCandidates = $this->findCandidates($slot, $settings);

            // Separar bloqueados antes de qualquer ordenação
            [$candidates, $blockedCandidates] = $this->partitionBlocked($allCandidates);

            foreach ($blockedCandidates as $blockedProduct) {
                $rejected->push([
                    'product' => $blockedProduct,
                    'reason' => PlacementFailureReason::Blocked,
                    'slot_id' => $slot->id,
                ]);
            }

            if ($candidates->isEmpty()) {
                $groupingsSemProduto++;
                $emptySlotIds[] = $slot->id;
                Log::debug('TemplatePlacementEngine: sem produto para slot', [
                    'category_id' => $slot->category_id,
                    'category_name' => $slot->relationLoaded('category') ? ($slot->category?->name ?? 'sem categoria') : 'não carregada',
                    'module' => $slot->module_number,
                    'shelf_order' => $slot->shelf_order,
                ]);

                continue;
            }

            $ordered = $this->applySpaceFallbackOrdering(
                $this->orderCandidates($candidates, $slot, $section, $shelf),
                $slot,
            );

            // Suporte a prateleiras compartilhadas: múltiplos slots (de categorias diferentes)
            // podem apontar para o mesmo par (module_number, shelf_order). O segundo slot começa
            // após o espaço já ocupado pelo primeiro, adensando micro-categorias no espaço livre.
            $shelfId = $shelf->getKey();
            $alreadyOccupied = (float) ($occupiedPerShelf[$shelfId] ?? 0.0);
            $shelfTotalWidth = $this->getShelfAvailableWidth($section);
            $available = max(0.0, $shelfTotalWidth - $alreadyOccupied);
            $startPosition = (int) round($alreadyOccupied);

            // Reserva de espaço para prateleira compartilhada: quando este é o PRIMEIRO slot
            // de uma prateleira que tem dois slots (primário + micro), limita o disponível para
            // que o expandFacings não consuma 100% da largura antes da micro-categoria ser processada.
            // A mesma fração (MICRO_CATEGORY_WIDTH_THRESHOLD) usada pelo SlotPlanBuilder para
            // detectar micro-categorias é usada aqui como cota mínima reservada para elas.
            if (isset($sharedShelfFirstSlotIds[$slot->id]) && $alreadyOccupied < 1.0) {
                $reservedForMicro = $shelfTotalWidth * SlotPlanBuilder::MICRO_CATEGORY_WIDTH_THRESHOLD;
                $available = max(0.0, $available - $reservedForMicro);
                Log::debug('TemplatePlacementEngine: espaço reservado para categoria compartilhada', [
                    'slot_id' => $slot->id,
                    'category_id' => $slot->category_id,
                    'shelf_total_cm' => round($shelfTotalWidth, 1),
                    'reservado_cm' => round($reservedForMicro, 1),
                    'disponivel_apos_reserva_cm' => round($available, 1),
                ]);
            }

            // Vão livre da prateleira: produtos mais altos são rejeitados (HeightExceedsShelf).
            // Clearance <= 0 significa dado físico ausente/inconsistente → checagem desativada.
            $sectionKey = (string) $section->getKey();
            $clearancesBySection[$sectionKey] ??= $this->greedyPlacer->shelfClearances($section);
            $clearance = $clearancesBySection[$sectionKey][$shelfId] ?? 0.0;

            $slotResult = $this->distributeInShelf(
                $ordered,
                $section,
                $shelf,
                $slot,
                $available,
                $startPosition,
                $clearance > 0 ? $clearance : null,
            );

            // Atualiza espaço ocupado nesta prateleira para o próximo slot compartilhado
            $newlyOccupied = (float) $slotResult['placed']->sum('width');
            $occupiedPerShelf[$shelfId] = $alreadyOccupied + $newlyOccupied;

            foreach ($slotResult['placed'] as $seg) {
                foreach ($seg->layers as $layer) {
                    $this->globalPlacedProductIds[$layer->productId] = true;
                }
            }

            foreach ($slotResult['rejected'] as $rejection) {
                if ($rejection['reason'] === PlacementFailureReason::MissingDimensions
                    && $rejection['product'] !== null) {
                    $this->globalPlacedProductIds[$rejection['product']->id] = true;
                }
            }

            // Re-etiquetar obrigatórios sem espaço para motivo específico
            $slotRejected = collect($slotResult['rejected'])->map(function (array $item): array {
                if ($item['reason'] === PlacementFailureReason::NoHorizontalSpace
                    && $item['product'] !== null
                    && isset($this->mandatoryProductIds[$item['product']->id])) {
                    return array_merge($item, ['reason' => PlacementFailureReason::MandatoryNoSpace]);
                }

                return $item;
            });

            $placed = $placed->merge($slotResult['placed']);
            $rejected = $rejected->merge($slotRejected);
            $allPlacedExplanations = array_merge($allPlacedExplanations, $slotResult['placed_explanations']);

            // Para slots compartilhados: largura_total reflete o espaço disponível PARA ESTE SLOT
            // (não a largura bruta da prateleira), e largura_livre o que sobrou após ele.
            $occupied = round($newlyOccupied, 1);
            $livre = round(max(0.0, $available - $occupied), 1);
            $slotAnalysis[] = [
                'slot_id' => $slot->id,
                'category_id' => $slot->category_id,
                'category_name' => $slot->category?->name ?? $slot->category_id,
                'role' => $slot->effectiveRole()?->value,
                'module_number' => $slot->module_number,
                'shelf_order' => $slot->shelf_order,
                'shelf_id' => $shelfId,
                'largura_total' => round($available, 1),
                'largura_usada' => $occupied,
                'largura_livre' => $livre,
                'percentual_uso' => $available > 0 ? (int) round(($occupied / $available) * 100) : 0,
                'produtos_posicionados' => $slotResult['placed']->count(),
                'produtos_rejeitados' => $slotResult['rejected']->where('reason', PlacementFailureReason::NoHorizontalSpace)->count(),
                'rejeitados_sem_dimensao' => $slotResult['rejected']->where('reason', PlacementFailureReason::MissingDimensions)->count(),
                'produtos_rejeitados_nomes' => $slotResult['rejected']
                    ->filter(fn ($r) => $r['product'] !== null && $r['reason'] === PlacementFailureReason::NoHorizontalSpace)
                    ->map(fn ($r) => $r['product']->name)
                    ->values()
                    ->toArray(),
            ];
        }

        // === OVERFLOW PASS ===
        // Produtos definitivamente rejeitados por falta de espaço são reposicionados em
        // qualquer prateleira da gôndola que ainda tenha espaço disponível, usando 1 frente.
        // Resolve o caso onde categorias menores (ex.: DE MILHO com 3 produtos) deixam
        // prateleiras vazias enquanto a categoria dominante (ex.: FAROFA) rejeita produtos
        // por falta de espaço nos seus próprios slots — o espaço ocioso passa a ser aproveitado.
        [$placed, $rejected] = $this->placeOverflow($placed, $rejected, $sections);

        if ($settings->planogramId !== null) {
            $this->recordSubtemplateUsed($settings->planogramId, $subtemplate->getKey());
        }

        $placedProductIds = $placed
            ->flatMap(fn ($seg) => $seg->layers->map(fn ($l) => $l->productId))
            ->flip()
            ->all();

        // Tentativas = eventos por slot (mesmo produto pode aparecer N vezes).
        // Definitivos = produtos únicos que não couberam em nenhum slot da sua categoria.
        $tentativasSemEspaco = $rejected->whereNotNull('product')->where('reason', PlacementFailureReason::NoHorizontalSpace)->count();
        $definitivosSemEspaco = $rejected
            ->filter(fn ($r) => $r['product'] !== null
                && $r['reason'] === PlacementFailureReason::NoHorizontalSpace
                && ! isset($placedProductIds[$r['product']->id]))
            ->unique(fn ($r) => $r['product']->id)
            ->count();

        Log::info('TemplatePlacementEngine: resultado', [
            'template_id' => $settings->templateId,
            'subtemplate_code' => $subtemplate->code,
            'num_modules_template' => $subtemplate->num_modules,
            'num_modules_gondola' => $sections->count(),
            'slots_processados' => $slots->count(),
            'slots_com_produto' => $slots->count() - $groupingsSemProduto - $rejected->whereNull('product')->count(),
            'slots_sem_matching' => $groupingsSemProduto,
            'slots_sem_prateleira' => $rejected->whereNull('product')->count(),
            'segmentos_criados' => $placed->count(),
            'tentativas_sem_espaco' => $tentativasSemEspaco,
            'rejeitados_sem_espaco' => $definitivosSemEspaco,
            'rejeitados_sem_dimensao' => $rejected->whereNotNull('product')->where('reason', PlacementFailureReason::MissingDimensions)->count(),
        ]);

        Log::info('TemplatePlacementEngine: análise de espaço por slot', [
            'slots' => collect($slotAnalysis)->map(fn ($s) => [
                'category_id' => $s['category_id'],
                'shelf_order' => $s['shelf_order'],
                'uso_percentual' => $s['percentual_uso'].'%',
                'largura_livre' => $s['largura_livre'].'cm',
                'rejeitados' => $s['produtos_rejeitados'],
                'sem_dimensao' => $s['rejeitados_sem_dimensao'],
            ])->toArray(),
        ]);

        $gondolaModules = $sections->count();
        $templateModules = $subtemplate->num_modules;

        $explanationReport = $this->buildExplanationReport($allPlacedExplanations, $rejected, $slotAnalysis);

        return new PlacementResult(
            placedSegments: $placed,
            rejectedProducts: $rejected,
            slotAnalysis: $slotAnalysis,
            modulesMismatch: $gondolaModules > $templateModules,
            templateModules: $templateModules,
            gondolaModules: $gondolaModules,
            subtemplateId: $subtemplate->getKey(),
            explanationReport: $explanationReport,
            emptySlotIds: $emptySlotIds,
        );
    }

    /**
     * Overflow pass: reposiciona produtos definitivamente rejeitados (NoHorizontalSpace) em
     * qualquer prateleira da gôndola com espaço disponível, usando apenas 1 frente por produto.
     *
     * Lógica:
     * 1. Identifica produtos únicos rejeitados por espaço que ainda não foram posicionados.
     * 2. Calcula o espaço ocupado por prateleira a partir dos segmentos já posicionados.
     * 3. Ordena prateleiras por maior espaço disponível (prioriza as mais vazias).
     * 4. Posiciona os produtos ordenados por ABC (A→B→C) nas primeiras prateleiras com espaço.
     * 5. Retorna as coleções atualizadas de placed e rejected.
     *
     * @param  Collection<int, PlacedSegment>  $placed
     * @param  Collection<int, array>  $rejected
     * @param  Collection<int, Section>  $sections
     * @return array{0: Collection<int, PlacedSegment>, 1: Collection<int, array>}
     */
    private function placeOverflow(
        Collection $placed,
        Collection $rejected,
        Collection $sections,
    ): array {
        // Identifica produtos já posicionados
        $placedIds = $placed
            ->flatMap(fn (PlacedSegment $seg) => $seg->layers->map(fn ($l) => $l->productId))
            ->flip()
            ->all();

        // Produtos únicos definitivamente rejeitados por falta de espaço horizontal
        $toRetry = $rejected
            ->filter(fn ($r) => $r['product'] !== null
                && $r['reason'] === PlacementFailureReason::NoHorizontalSpace
                && ! isset($placedIds[$r['product']->id]))
            ->unique(fn ($r) => $r['product']->id)
            ->values();

        if ($toRetry->isEmpty()) {
            return [$placed, $rejected];
        }

        // Espaço ocupado por prateleira (soma das larguras dos segmentos posicionados)
        $occupiedPerShelf = $placed
            ->groupBy(fn (PlacedSegment $seg) => $seg->shelfId)
            ->map(fn ($segs) => (float) $segs->sum('width'));

        // Mapa de prateleiras com espaço disponível: [section, shelf, remaining, occupied]
        $shelfMeta = [];

        foreach ($sections as $section) {
            $totalWidth = $this->getShelfAvailableWidth($section);
            $clearances = $this->greedyPlacer->shelfClearances($section);

            foreach ($section->shelves as $shelf) {
                $occupied = (float) ($occupiedPerShelf[$shelf->getKey()] ?? 0.0);
                $remaining = max(0.0, $totalWidth - $occupied);

                if ($remaining > 1.0) {
                    $shelfMeta[] = [
                        'section' => $section,
                        'shelf' => $shelf,
                        'remaining' => $remaining,
                        'occupied' => $occupied,
                        'clearance' => (float) ($clearances[$shelf->getKey()] ?? 0.0),
                    ];
                }
            }
        }

        if (empty($shelfMeta)) {
            return [$placed, $rejected];
        }

        // Ordena por maior espaço disponível (aproveita as mais vazias primeiro)
        usort($shelfMeta, fn ($a, $b) => $b['remaining'] <=> $a['remaining']);

        // Ordena produtos por ABC (A→B→C) para posicionar os de maior valor primeiro
        $retryOrdered = $toRetry
            ->sortBy(fn ($r) => match ($this->abcClassMap[$r['product']->id] ?? 'B') {
                'A' => 0,
                'B' => 1,
                'C' => 2,
                default => 1,
            })
            ->values();

        $overflowPlaced = collect();
        $overflowPlacedIds = [];
        $orderingOffset = $placed->count();

        foreach ($retryOrdered as $item) {
            $product = $item['product'];
            $singleWidth = (int) round($this->widthResolver->resolve($product));

            if ($singleWidth <= 0) {
                continue;
            }

            // Min-facings ABC: mesmo critério do placement principal (A→3, B→2, C→1).
            // O overflow não reduz abaixo do mínimo — produtos que não cabem com seus
            // facings mínimos em nenhuma prateleira permanecem rejeitados.
            $abcClass = $this->abcClassMap[$product->id] ?? '';
            $minFacings = SlotPlanBuilder::ABC_MIN_FACINGS[$abcClass] ?? SlotPlanBuilder::ABC_MIN_FACINGS[''];
            $widthWithFacings = (int) round($singleWidth * $minFacings);

            // Tenta a prateleira com maior espaço disponível
            foreach ($shelfMeta as $i => $meta) {
                if ($meta['remaining'] < $widthWithFacings) {
                    continue;
                }

                // Respeita o vão livre: não realoca produto em prateleira mais baixa que ele.
                // Clearance 0 = dado físico ausente → checagem desativada (legado).
                $productHeight = (float) ($product->height ?? 0);

                if ($meta['clearance'] > 0 && $productHeight > $meta['clearance']) {
                    continue;
                }

                $overflowPlaced->push(new PlacedSegment(
                    sectionId: $meta['section']->getKey(),
                    shelfId: $meta['shelf']->getKey(),
                    ordering: $orderingOffset++,
                    position: (int) round($meta['occupied']),
                    width: $widthWithFacings,
                    distributedWidth: $widthWithFacings,
                    layers: collect([
                        new PlacedLayer(
                            productId: $product->id,
                            ean: (string) ($product->ean ?? ''),
                            quantity: $minFacings,
                            height: 1,
                        ),
                    ]),
                ));

                $shelfMeta[$i]['occupied'] += $widthWithFacings;
                $shelfMeta[$i]['remaining'] -= $widthWithFacings;
                $this->globalPlacedProductIds[$product->id] = true;
                $overflowPlacedIds[$product->id] = true;

                // Re-ordena por espaço disponível após cada alocação (greedy)
                usort($shelfMeta, fn ($a, $b) => $b['remaining'] <=> $a['remaining']);

                break;
            }
        }

        if ($overflowPlaced->isEmpty()) {
            return [$placed, $rejected];
        }

        // Remove do rejected os produtos que foram colocados no overflow
        $updatedRejected = $rejected
            ->filter(fn ($r) => $r['product'] === null || ! isset($overflowPlacedIds[$r['product']->id]))
            ->values();

        // Produtos definitivamente sem posição: rejeitados E não colocados nem no pass principal
        // nem no overflow. Exclui falsos-positivos (produto rejeitado num slot mas alocado em outro).
        $allPlacedIds = $placedIds + $overflowPlacedIds;
        $aindaRejeitados = $updatedRejected
            ->filter(fn ($r) => $r['product'] !== null
                && $r['reason'] === PlacementFailureReason::NoHorizontalSpace
                && ! isset($allPlacedIds[$r['product']->id]))
            ->unique(fn ($r) => $r['product']->id)
            ->count();

        Log::info('TemplatePlacementEngine: overflow placement', [
            'tentativas' => $toRetry->count(),
            'colocados_overflow' => $overflowPlaced->count(),
            'ainda_rejeitados' => $aindaRejeitados,
        ]);

        return [$placed->merge($overflowPlaced), $updatedRejected];
    }

    private function resolveSubtemplate(PlacementSettings $settings): ?PlanogramSubtemplate
    {
        return PlanogramSubtemplate::withoutGlobalScope(TenantScope::class)
            ->where('template_id', $settings->templateId)
            ->where('num_modules', '<=', $settings->numModules)
            ->where('is_active', true)
            ->orderByDesc('num_modules')
            ->first();
    }

    /** @param Collection<int, Section> $sections */
    private function resolveSection(Collection $sections, int $moduleNumber): ?Section
    {
        return $sections->get($moduleNumber - 1);
    }

    /**
     * Converte shelf_order lógico (1=chão) para shelf física (shelf_position: 0=topo).
     * Fórmula: índice_físico = num_shelves - shelf_order
     */
    private function resolveShelf(Section $section, int $shelfOrder): ?Shelf
    {
        $shelves = $section->shelves->sortBy('shelf_position')->values();
        $numShelves = $shelves->count();
        $index = $numShelves - $shelfOrder;

        return $shelves[$index] ?? null;
    }

    /**
     * Separa produtos bloqueados por regra do pool de candidatos.
     * Retorna [candidatos_validos, bloqueados].
     *
     * @return array{0: Collection, 1: Collection}
     */
    private function partitionBlocked(Collection $candidates): array
    {
        if (empty($this->blockedProductIds) && empty($this->blockedBrands) && empty($this->blockedSubcategoryIds)) {
            return [$candidates, collect()];
        }

        $blocked = $candidates->filter(fn ($p) => $this->isProductBlocked($p));
        $valid = $candidates->reject(fn ($p) => $this->isProductBlocked($p));

        return [$valid->values(), $blocked->values()];
    }

    /**
     * Verifica se um produto está bloqueado por qualquer regra ativa.
     */
    private function isProductBlocked(mixed $product): bool
    {
        if (isset($this->blockedProductIds[$product->id])) {
            return true;
        }

        $brand = $product->brand ?? null;
        if ($brand !== null && isset($this->blockedBrands[$brand])) {
            return true;
        }

        $categoryId = $product->category_id ?? null;
        if ($categoryId !== null && isset($this->blockedSubcategoryIds[$categoryId])) {
            return true;
        }

        return false;
    }

    /**
     * Sobrepõe os atributos do slot com os valores do override da gôndola (apenas campos não-nulos).
     * Chamado antes de qualquer uso dos atributos do slot na geração.
     */
    private function applySlotOverride(PlanogramTemplateSlot $slot): void
    {
        if ($slot->category_id === null) {
            return;
        }

        $override = $this->gondolaSlotOverrides[$slot->category_id] ?? [];

        if ($override !== []) {
            $slot->forceFill($override);
        }
    }

    private function findCandidates(PlanogramTemplateSlot $slot, PlacementSettings $settings): Collection
    {
        if (! $slot->category_id) {
            Log::warning('TemplatePlacementEngine: slot sem category_id', [
                'slot_id' => $slot->id,
                'module' => $slot->module_number,
                'shelf_order' => $slot->shelf_order,
            ]);

            return collect();
        }

        $categoryIds = $this->getDescendantsCached($slot->category_id);

        return $settings->products->filter(
            fn ($product) => in_array($product->category_id, $categoryIds, true)
                && $product->status !== 'draft'
                && ! isset($this->globalPlacedProductIds[$product->id])
        )->values();
    }

    /** @return list<string> */
    private function getDescendantsCached(string $categoryId): array
    {
        return $this->descendantsCache[$categoryId]
            ??= Category::getDescendantIds($categoryId);
    }

    private function orderCandidates(Collection $products, PlanogramTemplateSlot $slot, ?Section $section = null, ?Shelf $shelf = null): Collection
    {
        // Delegado ao ProductOrderingService — mesma fonte usada por VisualReorderService e
        // ExposureRedistributeService, garantindo que geração e reordenação produzam a mesma ordem.
        $sorted = $this->ordering->orderBySlot($products, $slot, $this->abcClassMap, $this->zoneMetricsMap);

        // Zona térmica — aplicado por último para ser critério primário (stable sort)
        $sorted = $this->applyZoneOrdering($sorted, $section, $shelf);

        // Obrigatórios sempre no topo — garantem espaço antes de qualquer outro produto
        if (! empty($this->mandatoryProductIds)) {
            $sorted = $sorted->sortBy(fn ($p) => isset($this->mandatoryProductIds[$p->id]) ? 0 : 1);
        }

        return $sorted->values();
    }

    /**
     * Aplica critério de priorização por zona térmica.
     * Resolve a zona da prateleira e ordena produtos conforme a configuração do subtemplate.
     */
    private function applyZoneOrdering(Collection $products, ?Section $section, ?Shelf $shelf): Collection
    {
        if ($shelf === null || $section === null) {
            return $products;
        }

        $numShelves = $section->shelves->count();

        if ($numShelves === 0) {
            return $products;
        }

        $zone = ShelfZoneResolver::resolve($this->shelfSortedIndex($section, $shelf), $numShelves);

        $priority = match ($zone) {
            'hot' => $this->hotZonePriority,
            'cold' => $this->coldZonePriority,
            default => ZonePriority::None,
        };

        if ($priority === ZonePriority::None) {
            return $products;
        }

        return match ($priority) {
            ZonePriority::MaiorMargem => $products->sortByDesc(
                fn ($p) => (float) ($this->zoneMetricsMap[$p->id]['margem'] ?? 0)
            ),
            ZonePriority::MaiorGiro => $products->sortByDesc(
                fn ($p) => (float) ($this->zoneMetricsMap[$p->id]['giro'] ?? 0)
            ),
            ZonePriority::MaiorValorVendido => $products->sortByDesc(
                fn ($p) => (float) ($this->zoneMetricsMap[$p->id]['giro'] ?? 0)
                    * (float) ($p->price ?? 0)
            ),
            ZonePriority::CurvaA => $products->sortBy(
                fn ($p) => match ($this->abcClassMap[$p->id] ?? 'C') {
                    'A' => 0,
                    'B' => 1,
                    'C' => 2,
                    default => 1,
                }
            ),
            ZonePriority::MenorMargem => $products->sortBy(
                fn ($p) => (float) ($this->zoneMetricsMap[$p->id]['margem'] ?? 0)
            ),
            ZonePriority::ComplementarFria => $products->sortBy(
                fn ($p) => match ($this->abcClassMap[$p->id] ?? 'A') {
                    'C' => 0,
                    'B' => 1,
                    'A' => 2,
                    default => 1,
                }
            ),
            ZonePriority::MaiorVolume => $products->sortByDesc(
                fn ($p) => $this->sizeResolver->resolve($p)
            ),
            ZonePriority::MenorPrioridade => $products->sortBy(
                fn ($p) => (float) ($this->zoneMetricsMap[$p->id]['giro'] ?? 0)
            ),
            default => $products,
        };
    }

    /**
     * @return array{placed: Collection<int, PlacedSegment>, rejected: Collection<int, array{product: mixed, reason: PlacementFailureReason}>}
     */
    /**
     * Distribui produtos numa prateleira física, ocupando o espaço disponível.
     *
     * @param  float  $available  Largura disponível para este slot (já descontado o que
     *                            slots anteriores na mesma prateleira ocuparam).
     * @param  int  $startPosition  Posição inicial em cm — 0 para a primeira categoria,
     *                              >0 quando a prateleira é compartilhada com outra categoria.
     * @param  float|null  $maxProductHeight  Vão livre da prateleira em cm; produtos mais altos
     *                                        são rejeitados com HeightExceedsShelf. null = sem checagem
     *                                        (dado físico ausente — compatibilidade com gôndolas legadas).
     */
    private function distributeInShelf(
        Collection $products,
        Section $section,
        Shelf $shelf,
        PlanogramTemplateSlot $slot,
        float $available,
        int $startPosition = 0,
        ?float $maxProductHeight = null,
    ): array {
        /** @var array<int, array{product: mixed, facings: int, singleWidth: float, ordering: int}> $placedItems */
        $placedItems = [];
        $rejected = collect();
        $occupied = 0.0;
        $ordering = 0;

        // Phase 1: place with min_facings
        foreach ($products as $product) {
            $rawWidth = isset($product->width) ? (float) $product->width : null;

            if ($rawWidth === null || $rawWidth <= 0) {
                $rejected->push([
                    'product' => $product,
                    'reason' => PlacementFailureReason::MissingDimensions,
                    'slot_id' => $slot->id,
                ]);

                continue;
            }

            // Produto mais alto que o vão livre da prateleira não cabe fisicamente.
            $productHeight = (float) ($product->height ?? 0);

            if ($maxProductHeight !== null && $productHeight > $maxProductHeight) {
                $rejected->push([
                    'product' => $product,
                    'reason' => PlacementFailureReason::HeightExceedsShelf,
                    'slot_id' => $slot->id,
                ]);

                continue;
            }

            $facing = max($slot->min_facings, 1);
            $singleWidth = $this->widthResolver->resolve($product);
            $width = (int) round($singleWidth * $facing);

            if ($occupied + $width <= $available) {
                $placedItems[] = [
                    'product' => $product,
                    'facings' => $facing,
                    'singleWidth' => $singleWidth,
                    'ordering' => $ordering++,
                ];
                $occupied += $width;
            } else {
                // Log de diagnóstico quando o slot está vazio mas o produto não coube.
                if ($occupied < 0.01) {
                    if ($available <= 0.0) {
                        // Slot sem espaço algum: categoria primária expandiu até 100% antes da reserva
                        // ser aplicada, ou erro de adensamento no SlotPlanBuilder.
                        Log::debug('TemplatePlacementEngine: produto rejeitado — slot sem espaço (possível adensamento mal planejado)', [
                            'product_id' => $product->id,
                            'singleWidth' => $singleWidth,
                            'width_necessaria' => $width,
                            'available' => 0.0,
                            'slot_id' => $slot->id,
                            'categoria' => $slot->category_id,
                        ]);
                    } else {
                        // Slot com espaço, mas produto é mais largo que o disponível.
                        Log::debug('TemplatePlacementEngine: produto rejeitado — mais largo que espaço disponível no slot', [
                            'product_id' => $product->id,
                            'min_facings' => $facing,
                            'singleWidth' => $singleWidth,
                            'width_necessaria' => $width,
                            'available' => $available,
                            'slot_id' => $slot->id,
                            'dica' => 'Verificar min_facings do slot ou width do produto.',
                        ]);
                    }
                }

                $rejected->push([
                    'product' => $product,
                    'reason' => PlacementFailureReason::NoHorizontalSpace,
                    'slot_id' => $slot->id,
                ]);
            }
        }

        // Phase 2: expand facings with leftover space
        if ($slot->facing_expansion !== FacingExpansion::None && $placedItems !== []) {
            [$placedItems, $occupied] = $this->expandFacings($placedItems, $slot, $available, $occupied, $shelf);
        }

        // Anotar explicações dos produtos posicionados (após expansão de frentes)
        $minFacing = max($slot->min_facings, 1);
        $zone = $this->resolveZoneForShelf($section, $shelf);
        $placedExplanations = $this->buildPlacedExplanations($placedItems, $slot, $minFacing, $zone, $shelf);

        // Build readonly PlacedSegment DTOs
        // $x parte de $startPosition para acomodar prateleiras compartilhadas: quando outra
        // categoria já ocupa [0, startPosition), este slot começa a partir de startPosition.
        $placed = collect();
        $x = (float) $startPosition;

        foreach ($placedItems as $item) {
            $product = $item['product'];
            $facings = $item['facings'];
            $width = (int) round($item['singleWidth'] * $facings);

            $placed->push(new PlacedSegment(
                sectionId: $section->getKey(),
                shelfId: $shelf->getKey(),
                ordering: $item['ordering'],
                position: (int) round($x),
                width: $width,
                distributedWidth: $width,
                layers: collect([
                    new PlacedLayer(
                        productId: $product->id,
                        ean: (string) ($product->ean ?? ''),
                        quantity: $facings,
                        height: 1,
                    ),
                ]),
            ));
            $x += $width;
        }

        // Espelhar posições físicas quando o fluxo é direita → esquerda
        if ($this->flowDirection === FlowDirection::RightToLeft && $placed->isNotEmpty()) {
            $totalWidth = (int) round($x);
            $placed = $placed->map(fn (PlacedSegment $seg): PlacedSegment => new PlacedSegment(
                sectionId: $seg->sectionId,
                shelfId: $seg->shelfId,
                ordering: $seg->ordering,
                position: $totalWidth - $seg->position - $seg->width,
                width: $seg->width,
                distributedWidth: $seg->distributedWidth,
                layers: $seg->layers,
                shelfLevel: $seg->shelfLevel,
            ));
        }

        // Fallback only for NoHorizontalSpace — MissingDimensions must not be retried
        $noSpaceRejected = $rejected->where('reason', PlacementFailureReason::NoHorizontalSpace)->values();

        if ($noSpaceRejected->isNotEmpty()) {
            $fallback = $this->applyFallback($noSpaceRejected, $available - $occupied, $slot, $section, $shelf, $ordering);
            $placed = $placed->merge($fallback['placed']);
            $noSpaceRejected = $fallback['remaining'];
        }

        // Demais motivos (MissingDimensions, HeightExceedsShelf) não passam pelo fallback de espaço.
        $otherRejected = $rejected
            ->filter(fn (array $r): bool => $r['reason'] !== PlacementFailureReason::NoHorizontalSpace)
            ->values();

        return [
            'placed' => $placed,
            'rejected' => $noSpaceRejected->merge($otherRejected),
            'placed_explanations' => $placedExplanations,
        ];
    }

    /**
     * Reordena o pool conforme a regra de falta de espaço do slot.
     *
     * ReduceC: produtos curva C vão para o fim da fila (rejeitados primeiro).
     * RemoveDog: produtos lagging (retardatários) vão para o fim da fila.
     * Compartilhado entre o caminho horizontal e a blocagem vertical.
     */
    private function applySpaceFallbackOrdering(Collection $ordered, PlanogramTemplateSlot $slot): Collection
    {
        if ($slot->space_fallback === SpaceFallback::ReduceC && ! empty($this->abcClassMap)) {
            $ordered = $ordered->sortBy(fn ($p) => match ($this->abcClassMap[$p->id] ?? 'B') {
                'A' => 0,
                'B' => 1,
                'C' => 2,
                default => 1,
            })->values();
        }

        if ($slot->space_fallback === SpaceFallback::RemoveDog && ! empty($this->bcgMap)) {
            $ordered = $ordered->sortBy(fn ($p) => match ($this->bcgMap[$p->id] ?? 'anchor') {
                'leader' => 0,
                'rising' => 1,
                'anchor' => 2,
                'lagging' => 3,
                default => 2,
            })->values();
        }

        return $ordered;
    }

    /**
     * Agrupa slots elegíveis para blocagem vertical (layout_orientation = vertical).
     *
     * Um grupo reúne slots do mesmo (module_number, category_id) cuja categoria ocupa
     * 2+ prateleiras consecutivas. Regras de elegibilidade:
     *  - prateleira compartilhada (micro-categoria adensada) nunca entra — o adensamento
     *    quebraria o alinhamento das colunas;
     *  - shelf_orders consecutivos (bloco visual contíguo);
     *  - o chão (shelf_order 1) PARTICIPA da blocagem: as colunas atravessam todas as
     *    prateleiras da categoria, como nas gôndolas de referência — excluí-lo cortava
     *    a capacidade do grupo e empurrava o excedente para o overflow horizontal.
     *
     * @param  Collection<int, PlanogramTemplateSlot>  $slots
     * @param  array<string, int>  $positionSlotCounts  [module_shelf => qtde de slots na posição]
     * @return list<list<PlanogramTemplateSlot>>
     */
    private function buildVerticalGroups(Collection $slots, array $positionSlotCounts): array
    {
        $byModuleCategory = [];

        foreach ($slots as $slot) {
            $posKey = $slot->module_number.'_'.$slot->shelf_order;

            if (($positionSlotCounts[$posKey] ?? 1) >= 2 || $slot->category_id === null) {
                continue;
            }

            $byModuleCategory[$slot->module_number.'|'.$slot->category_id][] = $slot;
        }

        $eligible = [];

        foreach ($byModuleCategory as $group) {
            usort($group, fn (PlanogramTemplateSlot $a, PlanogramTemplateSlot $b): int => $a->shelf_order <=> $b->shelf_order);

            if (count($group) < 2) {
                continue;
            }

            $consecutive = true;
            for ($i = 1; $i < count($group); $i++) {
                if ($group[$i]->shelf_order !== $group[$i - 1]->shelf_order + 1) {
                    $consecutive = false;
                    break;
                }
            }

            if ($consecutive) {
                $eligible[] = $group;
            }
        }

        return $eligible;
    }

    /**
     * Posiciona um grupo de slots como colunas verticais por marca.
     *
     * Cada marca recebe uma COLUNA de largura proporcional à sua demanda
     * (com piso = produto mais largo da marca), e a coluna é preenchida de
     * cima para baixo atravessando as prateleiras do grupo — mesma faixa de X
     * em todas, formando o bloco visual alinhado.
     *
     * Produtos que não cabem na sua coluna viram NoHorizontalSpace e são
     * recolocados pelo overflow pass global. A expansão de frentes acontece
     * por célula (marca × prateleira) e nunca invade a coluna vizinha.
     *
     * @param  list<PlanogramTemplateSlot>  $group  Slots do mesmo módulo+categoria (shelf_order ASC)
     * @param  Collection<int, Section>  $sections
     * @param  array<string, array<string, float>>  $clearancesBySection  Cache de vãos livres (por referência)
     * @return array{placed: Collection, rejected: Collection, slot_analysis: list<array<string, mixed>>, placed_explanations: list<array<string, mixed>>, occupied_per_shelf: array<string, float>, empty_slot_ids: list<string>}
     */
    private function placeVerticalGroup(
        array $group,
        Collection $sections,
        PlacementSettings $settings,
        array &$clearancesBySection,
    ): array {
        $rejected = collect();
        $empty = [
            'placed' => collect(),
            'rejected' => $rejected,
            'slot_analysis' => [],
            'placed_explanations' => [],
            'occupied_per_shelf' => [],
            'empty_slot_ids' => [],
        ];

        foreach ($group as $slot) {
            $this->applySlotOverride($slot);
        }

        $firstSlot = $group[0];
        $section = $this->resolveSection($sections, $firstSlot->module_number);

        if ($section === null) {
            foreach ($group as $slot) {
                $rejected->push([
                    'product' => null,
                    'reason' => PlacementFailureReason::NoShelfAtLevel,
                    'slot_id' => $slot->id,
                ]);
            }

            return $empty;
        }

        $sectionKey = (string) $section->getKey();
        $clearancesBySection[$sectionKey] ??= $this->greedyPlacer->shelfClearances($section);

        // Linhas da blocagem: topo primeiro (shelf_order maior = mais alto).
        // O preenchimento desce prateleira a prateleira dentro de cada coluna.
        /** @var list<array{slot: PlanogramTemplateSlot, shelf: Shelf, clearance: float|null}> $rows */
        $rows = [];

        foreach (array_reverse($group) as $slot) {
            $shelf = $this->resolveShelf($section, $slot->shelf_order);

            if ($shelf === null) {
                $rejected->push([
                    'product' => null,
                    'reason' => PlacementFailureReason::NoShelfAtLevel,
                    'slot_id' => $slot->id,
                ]);

                continue;
            }

            $clearance = $clearancesBySection[$sectionKey][$shelf->getKey()] ?? 0.0;
            $rows[] = ['slot' => $slot, 'shelf' => $shelf, 'clearance' => $clearance > 0 ? $clearance : null];
        }

        if ($rows === []) {
            return $empty;
        }

        // Pool de candidatos: o primeiro slot representa o grupo (mesma categoria)
        $allCandidates = $this->findCandidates($firstSlot, $settings);
        [$candidates, $blockedCandidates] = $this->partitionBlocked($allCandidates);

        foreach ($blockedCandidates as $blockedProduct) {
            $rejected->push([
                'product' => $blockedProduct,
                'reason' => PlacementFailureReason::Blocked,
                'slot_id' => $firstSlot->id,
            ]);
        }

        if ($candidates->isEmpty()) {
            $empty['empty_slot_ids'] = array_map(fn (PlanogramTemplateSlot $s): string => $s->id, $group);

            return $empty;
        }

        $ordered = $this->applySpaceFallbackOrdering(
            $this->orderCandidates($candidates, $firstSlot, $section, $rows[0]['shelf']),
            $firstSlot,
        );

        $shelfTotalWidth = $this->getShelfAvailableWidth($section);
        $minFacings = max($firstSlot->min_facings, 1);

        // 1. Partição por marca — ordem de primeira aparição no pool ordenado
        //    (respeita score/ABC: a melhor marca fica na primeira coluna do fluxo)
        /** @var array<string, list<mixed>> $brandProducts */
        $brandProducts = [];

        foreach ($ordered as $product) {
            $rawWidth = isset($product->width) ? (float) $product->width : null;

            if ($rawWidth === null || $rawWidth <= 0) {
                $rejected->push([
                    'product' => $product,
                    'reason' => PlacementFailureReason::MissingDimensions,
                    'slot_id' => $firstSlot->id,
                ]);
                // Mesma semântica do caminho horizontal: sem dimensão não tenta outros slots
                $this->globalPlacedProductIds[$product->id] = true;

                continue;
            }

            $brandProducts[$product->brand ?? 'SEM MARCA'][] = $product;
        }

        if ($brandProducts === []) {
            $empty['empty_slot_ids'] = array_map(fn (PlanogramTemplateSlot $s): string => $s->id, $group);

            return $empty;
        }

        // 2. Demanda e piso (produto mais largo × min_facings) por marca
        $demand = [];
        $minViable = [];

        foreach ($brandProducts as $brand => $products) {
            $demand[$brand] = 0.0;
            $minViable[$brand] = 0.0;

            foreach ($products as $product) {
                $w = $this->widthResolver->resolve($product) * $minFacings;
                $demand[$brand] += $w;
                $minViable[$brand] = max($minViable[$brand], $w);
            }
        }

        // 2b. Se nem os pisos cabem, remove a marca de MENOR demanda até caber
        //     (produtos removidos viram NoHorizontalSpace → overflow pass)
        $leftovers = [];

        while (count($demand) > 1 && array_sum($minViable) > $shelfTotalWidth) {
            $smallest = array_keys($demand, min($demand))[0];

            foreach ($brandProducts[$smallest] as $product) {
                $leftovers[] = $product;
            }

            unset($brandProducts[$smallest], $demand[$smallest], $minViable[$smallest]);
        }

        // 2c. Largura de coluna = piso + sobra distribuída proporcionalmente à demanda.
        // Cap na largura da prateleira: protege o caso de marca única com piso maior que a prateleira
        // (o produto largo demais cai no check de largura da coluna e vai para o overflow).
        $rest = max(0.0, $shelfTotalWidth - array_sum($minViable));
        $totalDemand = max(array_sum($demand), 0.001);
        $colWidth = [];

        foreach ($demand as $brand => $brandDemand) {
            $colWidth[$brand] = min($shelfTotalWidth, $minViable[$brand] + $rest * ($brandDemand / $totalDemand));
        }

        // 3. Faixas de X por coluna (cálculo sempre em LTR; espelhamento no final)
        $colStart = [];
        $x = 0.0;

        foreach ($colWidth as $brand => $width) {
            $colStart[$brand] = $x;
            $x += $width;
        }

        // 4. Preenchimento coluna por coluna, de cima para baixo.
        //    Célula = (linha/prateleira × marca); o X de cada item é derivado
        //    da posição acumulada DENTRO da célula após a expansão de frentes.
        /** @var array<int, array<string, array<int, array{product: mixed, facings: int, singleWidth: float, ordering: int}>>> $cells */
        $cells = [];
        $rowCount = count($rows);
        $maxClearance = max(array_map(fn (array $row): float => $row['clearance'] ?? PHP_FLOAT_MAX, $rows));

        foreach ($brandProducts as $brand => $products) {
            // Ocupação por linha desta coluna: cada produto busca, de cima para baixo,
            // a primeira célula com espaço — produto estreito preenche célula parcial
            // em vez de virar sobra só porque um produto largo anterior não coube
            $rowOccupied = array_fill(0, $rowCount, 0.0);

            foreach ($products as $product) {
                $productHeight = (float) ($product->height ?? 0);

                // Mais alto que TODOS os vãos do grupo: não cabe fisicamente em nenhuma linha
                if ($productHeight > $maxClearance) {
                    $rejected->push([
                        'product' => $product,
                        'reason' => PlacementFailureReason::HeightExceedsShelf,
                        'slot_id' => $firstSlot->id,
                    ]);

                    continue;
                }

                $singleWidth = $this->widthResolver->resolve($product);
                $width = (int) round($singleWidth * $minFacings);

                // Mais largo que a própria coluna: nem 1 conjunto de frentes mínimas cabe
                if ($width > $colWidth[$brand] + 0.01) {
                    $leftovers[] = $product;

                    continue;
                }

                $targetRow = null;

                for ($rowIdx = 0; $rowIdx < $rowCount; $rowIdx++) {
                    if ($rowOccupied[$rowIdx] + $width > $colWidth[$brand] + 0.01) {
                        continue;
                    }

                    if ($rows[$rowIdx]['clearance'] !== null && $productHeight > $rows[$rowIdx]['clearance']) {
                        continue;
                    }

                    $targetRow = $rowIdx;
                    break;
                }

                if ($targetRow === null) {
                    // Nenhuma célula da coluna comporta o produto: vai para o overflow pass
                    $leftovers[] = $product;

                    continue;
                }

                $cells[$targetRow][$brand][] = [
                    'product' => $product,
                    'facings' => $minFacings,
                    'singleWidth' => $singleWidth,
                    'ordering' => 0, // reatribuído por prateleira após o espelhamento
                ];
                $rowOccupied[$targetRow] += $width;
            }
        }

        // 5. Expansão de frentes POR CÉLULA — nunca invade a coluna vizinha
        if ($firstSlot->facing_expansion !== FacingExpansion::None) {
            foreach ($cells as $rowIdx => $brandsInRow) {
                foreach ($brandsInRow as $brand => $items) {
                    $occupied = 0.0;

                    foreach ($items as $item) {
                        $occupied += round($item['singleWidth'] * $item['facings']);
                    }

                    [$cells[$rowIdx][$brand]] = $this->expandFacings(
                        $items,
                        $rows[$rowIdx]['slot'],
                        $colWidth[$brand],
                        $occupied,
                        $rows[$rowIdx]['shelf'],
                    );
                }
            }

            // 5b. Replicação vertical: linha sem nenhum item da marca herda os itens da
            //     linha preenchida mais próxima ACIMA — os mesmos SKUs se repetem prateleira
            //     a prateleira (gôndola de referência), em vez de deixar linhas do bloco vazias.
            //     Roda após a expansão para que larguras e X fiquem idênticos entre as linhas.
            $replicatedRows = 0;

            foreach (array_keys($brandProducts) as $brand) {
                $sourceItems = null;

                for ($rowIdx = 0; $rowIdx < $rowCount; $rowIdx++) {
                    $items = $cells[$rowIdx][$brand] ?? [];

                    if ($items !== []) {
                        $sourceItems = $items;

                        continue;
                    }

                    if ($sourceItems === null) {
                        continue;
                    }

                    // Só replica o que cabe no vão da linha destino
                    $clearance = $rows[$rowIdx]['clearance'];
                    $replicable = array_values(array_filter(
                        $sourceItems,
                        fn (array $item): bool => $clearance === null
                            || (float) ($item['product']->height ?? 0) <= $clearance,
                    ));

                    if ($replicable !== []) {
                        $cells[$rowIdx][$brand] = $replicable;
                        $replicatedRows++;
                    }
                }
            }

            if ($replicatedRows > 0) {
                Log::debug('TemplatePlacementEngine: blocagem vertical — linhas replicadas', [
                    'module' => $firstSlot->module_number,
                    'category_id' => $firstSlot->category_id,
                    'linhas_replicadas' => $replicatedRows,
                ]);
            }
        }

        // 6. Construção dos PlacedSegments — a "coluna" é só a coincidência do X inicial
        $placed = collect();
        $placedExplanations = [];
        $occupiedPerShelf = [];
        $rowStats = array_fill(0, $rowCount, ['placed' => 0, 'occupied' => 0.0]);

        foreach ($cells as $rowIdx => $brandsInRow) {
            $row = $rows[$rowIdx];

            foreach ($colStart as $brand => $startX) {
                $items = $brandsInRow[$brand] ?? [];

                if ($items === []) {
                    continue;
                }

                $cellX = $startX;

                foreach ($items as $item) {
                    $width = (int) round($item['singleWidth'] * $item['facings']);

                    $placed->push(new PlacedSegment(
                        sectionId: $section->getKey(),
                        shelfId: $row['shelf']->getKey(),
                        ordering: 0, // reatribuído após o espelhamento
                        position: (int) round($cellX),
                        width: $width,
                        distributedWidth: $width,
                        layers: collect([
                            new PlacedLayer(
                                productId: $item['product']->id,
                                ean: (string) ($item['product']->ean ?? ''),
                                quantity: $item['facings'],
                                height: 1,
                            ),
                        ]),
                    ));

                    $this->globalPlacedProductIds[$item['product']->id] = true;
                    $cellX += $width;
                    $rowStats[$rowIdx]['placed']++;
                    $rowStats[$rowIdx]['occupied'] += $width;
                }

                $zone = $this->resolveZoneForShelf($section, $row['shelf']);
                $placedExplanations = array_merge(
                    $placedExplanations,
                    $this->buildPlacedExplanations($items, $row['slot'], $minFacings, $zone, $row['shelf']),
                );
            }

            $occupiedPerShelf[$row['shelf']->getKey()] = $rowStats[$rowIdx]['occupied'];
        }

        // 7. Espelhamento RightToLeft: colunas inteiras invertem mantendo o alinhamento,
        //    pois todas as prateleiras usam a mesma largura total de referência
        if ($this->flowDirection === FlowDirection::RightToLeft && $placed->isNotEmpty()) {
            $mirrorWidth = (int) round($shelfTotalWidth);
            $placed = $placed->map(fn (PlacedSegment $seg): PlacedSegment => new PlacedSegment(
                sectionId: $seg->sectionId,
                shelfId: $seg->shelfId,
                ordering: $seg->ordering,
                position: $mirrorWidth - $seg->position - $seg->width,
                width: $seg->width,
                distributedWidth: $seg->distributedWidth,
                layers: $seg->layers,
                shelfLevel: $seg->shelfLevel,
            ));
        }

        // 8. Ordering sequencial por prateleira (contrato do PlacedSegment: índice horizontal)
        $placed = $placed
            ->groupBy('shelfId')
            ->flatMap(fn (Collection $segments) => $segments
                ->sortBy('position')
                ->values()
                ->map(fn (PlacedSegment $seg, int $i): PlacedSegment => new PlacedSegment(
                    sectionId: $seg->sectionId,
                    shelfId: $seg->shelfId,
                    ordering: $i,
                    position: $seg->position,
                    width: $seg->width,
                    distributedWidth: $seg->distributedWidth,
                    layers: $seg->layers,
                    shelfLevel: $seg->shelfLevel,
                )))
            ->values();

        // 9. Sobras → NoHorizontalSpace (o overflow pass global tenta recolocá-las);
        //    obrigatórios ganham o motivo específico, como no caminho horizontal
        foreach ($leftovers as $product) {
            $rejected->push([
                'product' => $product,
                'reason' => isset($this->mandatoryProductIds[$product->id])
                    ? PlacementFailureReason::MandatoryNoSpace
                    : PlacementFailureReason::NoHorizontalSpace,
                'slot_id' => $firstSlot->id,
            ]);
        }

        // 10. Análise de espaço por slot do grupo (mesmo formato do caminho horizontal)
        $slotAnalysis = [];

        foreach ($rows as $rowIdx => $row) {
            $occupied = round($rowStats[$rowIdx]['occupied'], 1);
            $slotAnalysis[] = [
                'slot_id' => $row['slot']->id,
                'category_id' => $row['slot']->category_id,
                'category_name' => $row['slot']->category?->name ?? $row['slot']->category_id,
                'role' => $row['slot']->effectiveRole()?->value,
                'module_number' => $row['slot']->module_number,
                'shelf_order' => $row['slot']->shelf_order,
                'shelf_id' => $row['shelf']->getKey(),
                'largura_total' => round($shelfTotalWidth, 1),
                'largura_usada' => $occupied,
                'largura_livre' => round(max(0.0, $shelfTotalWidth - $occupied), 1),
                'percentual_uso' => $shelfTotalWidth > 0 ? (int) round(($occupied / $shelfTotalWidth) * 100) : 0,
                'produtos_posicionados' => $rowStats[$rowIdx]['placed'],
                'produtos_rejeitados' => 0, // sobras são contabilizadas no slot líder do grupo
                'rejeitados_sem_dimensao' => 0,
                'produtos_rejeitados_nomes' => [],
            ];
        }

        if ($slotAnalysis !== []) {
            $slotAnalysis[0]['produtos_rejeitados'] = count($leftovers);
            $slotAnalysis[0]['produtos_rejeitados_nomes'] = array_values(array_map(
                fn ($product) => $product->name,
                $leftovers,
            ));
        }

        Log::info('TemplatePlacementEngine: blocagem vertical aplicada', [
            'module' => $firstSlot->module_number,
            'category_id' => $firstSlot->category_id,
            'prateleiras' => $rowCount,
            'colunas' => array_map(fn (float $w): float => round($w, 1), $colWidth),
            'posicionados' => $placed->count(),
            'sobras_overflow' => count($leftovers),
            // Sobra com déficit > 0 é limitação física (demanda excede o bloco), não defeito de empacotamento
            'demanda_cm' => round(array_sum($demand), 1),
            'capacidade_cm' => round($shelfTotalWidth * $rowCount, 1),
        ]);

        return [
            'placed' => $placed,
            'rejected' => $rejected,
            'slot_analysis' => $slotAnalysis,
            'placed_explanations' => $placedExplanations,
            'occupied_per_shelf' => $occupiedPerShelf,
            'empty_slot_ids' => [],
        ];
    }

    /**
     * Phase 2: distribute leftover shelf space as extra facings.
     *
     * Respeita `max_facings` como teto absoluto e os limites relativos de participação
     * (`max_share_per_sku`, `max_share_per_brand`, `max_share_per_subcategory`) como tetos adicionais.
     * O menor limite entre os dois vence. Limites null são ignorados (comportamento original).
     *
     * Quando o slot tem `use_target_stock` ligado, aplica também um teto por produto derivado
     * do estoque alvo (ver `targetStockFacingCap`): a expansão para ao atingir as frentes
     * necessárias para cobrir o alvo, mesmo que ainda sobre espaço na prateleira.
     *
     * @param  array<int, array{product: mixed, facings: int, singleWidth: float, ordering: int}>  $placedItems
     * @return array{0: array<int, array{product: mixed, facings: int, singleWidth: float, ordering: int}>, 1: float}
     */
    private function expandFacings(array $placedItems, PlanogramTemplateSlot $slot, float $available, float $occupied, Shelf $shelf): array
    {
        $maxFacings = max($slot->max_facings, 1);
        $remainingWidth = $available - $occupied;

        if ($remainingWidth <= 0 || $maxFacings <= 1) {
            return [$placedItems, $occupied];
        }

        $expansion = $slot->facing_expansion ?? FacingExpansion::Score;
        $expansionOrder = $this->expansionOrder($placedItems, $expansion);

        // Teto de frentes por estoque alvo (opt-in via use_target_stock). Pré-calculado por
        // índice para evitar recomputar no laço; produtos sem alvo ficam de fora (sem teto).
        $minFacings = max($slot->min_facings ?? 1, 1);
        $facingCap = [];

        if ($slot->use_target_stock) {
            foreach ($placedItems as $idx => $item) {
                $cap = $this->targetStockFacingCap($item['product'], $shelf, $minFacings);

                if ($cap !== null) {
                    $facingCap[$idx] = $cap;
                }
            }
        }

        // Round-robin: give +1 facing per pass until space runs out or all hit max_facings
        $changed = true;

        while ($changed && $remainingWidth > 0) {
            $changed = false;

            foreach ($expansionOrder as $idx) {
                if ($placedItems[$idx]['facings'] >= $maxFacings) {
                    continue;
                }

                // Estoque alvo já coberto: não expande mais, mesmo com espaço livre.
                if (isset($facingCap[$idx]) && $placedItems[$idx]['facings'] >= $facingCap[$idx]) {
                    continue;
                }

                $singleWidth = $placedItems[$idx]['singleWidth'];

                if ($remainingWidth < $singleWidth) {
                    continue;
                }

                if ($this->violatesParticipationLimit($placedItems, $idx, $slot, $available)) {
                    continue;
                }

                $placedItems[$idx]['facings']++;
                $remainingWidth -= $singleWidth;
                $occupied += $singleWidth;
                $changed = true;
            }
        }

        return [$placedItems, $occupied];
    }

    /**
     * Calcula o teto de frentes que cobre o estoque alvo de um produto.
     *
     * Converte o alvo (em unidades) para frentes pela capacidade de profundidade da prateleira:
     * cada frente comporta `floor(shelf_depth / product_depth)` unidades em profundidade (mínimo 1).
     * Sem dados de profundidade válidos (produto ou prateleira), assume 1 unidade por frente.
     *
     * A premissa de profundidade é limitada pelo config `auto_planogram.target_stock.max_facing_depth`
     * (unidades de fundo por frente), pois a capacidade física pura costuma ser irrealista para
     * exposição (ex.: 8 de fundo) e travaria produtos de alvo baixo em 1 frente, deixando a
     * prateleira vazia. `null` no config = sem teto (capacidade física pura).
     *
     * Retorna `null` quando o produto não tem estoque alvo no mapa (sem teto a aplicar).
     * O teto nunca fica abaixo de `$minFacings` para não conflitar com a frente mínima do slot.
     */
    private function targetStockFacingCap(mixed $product, Shelf $shelf, int $minFacings): ?int
    {
        $target = $this->targetStockMap[$product->id] ?? null;

        if ($target === null || $target <= 0) {
            return null;
        }

        $productDepth = (float) ($product->depth ?? 0);
        $shelfDepth = (float) ($shelf->shelf_depth ?? config('plannerate.shelfDepth', 40));

        $unitsPerFacing = ($productDepth > 0 && $shelfDepth > 0)
            ? max(1, (int) floor($shelfDepth / $productDepth))
            : 1;

        // Limita a premissa de profundidade a um valor de exposição típico (config), para que
        // alvos pequenos gerem mais frentes em vez de travar em 1 e esvaziar a prateleira.
        $maxFacingDepth = config('plannerate.auto_planogram.target_stock.max_facing_depth');

        if ($maxFacingDepth !== null && (int) $maxFacingDepth > 0) {
            $unitsPerFacing = min($unitsPerFacing, (int) $maxFacingDepth);
        }

        return max($minFacings, (int) ceil($target / $unitsPerFacing));
    }

    /**
     * Verifica se dar +1 frente ao item $idx violaria algum limite relativo de participação.
     *
     * Referência de largura: slot disponível ($available) para todos os três níveis,
     * pois o engine opera dentro de um único slot por vez.
     * Limites null → ignorados (sem restrição).
     *
     * @param  array<int, array{product: mixed, facings: int, singleWidth: float, ordering: int}>  $placedItems
     */
    private function violatesParticipationLimit(array $placedItems, int $idx, PlanogramTemplateSlot $slot, float $available): bool
    {
        if ($available <= 0) {
            return false;
        }

        $item = $placedItems[$idx];
        $singleWidth = $item['singleWidth'];
        $newFacings = $item['facings'] + 1;
        $newSkuWidth = $singleWidth * $newFacings;

        // Limite por SKU: porcentagem do slot que um único produto pode ocupar
        if ($slot->max_share_per_sku !== null && $slot->max_share_per_sku > 0) {
            if (($newSkuWidth / $available) * 100 > $slot->max_share_per_sku) {
                return true;
            }
        }

        // Limite por marca: porcentagem do slot que todos os produtos de uma marca podem ocupar
        if ($slot->max_share_per_brand !== null && $slot->max_share_per_brand > 0) {
            $brand = $item['product']->brand ?? null;
            $currentBrandWidth = 0.0;

            foreach ($placedItems as $i => $p) {
                if ($i !== $idx && ($p['product']->brand ?? null) === $brand) {
                    $currentBrandWidth += $p['singleWidth'] * $p['facings'];
                }
            }

            // Soma a largura atual deste item (antes do incremento) + mais 1 facing
            $currentBrandWidth += $singleWidth * $item['facings'];
            $newBrandWidth = $currentBrandWidth + $singleWidth;

            if (($newBrandWidth / $available) * 100 > $slot->max_share_per_brand) {
                return true;
            }
        }

        // Limite por subcategoria: porcentagem do slot que todos os produtos de uma subcategoria podem ocupar
        if ($slot->max_share_per_subcategory !== null && $slot->max_share_per_subcategory > 0) {
            $subcatId = $item['product']->category_id ?? null;
            $currentSubcatWidth = 0.0;

            foreach ($placedItems as $i => $p) {
                if ($i !== $idx && ($p['product']->category_id ?? null) === $subcatId) {
                    $currentSubcatWidth += $p['singleWidth'] * $p['facings'];
                }
            }

            $currentSubcatWidth += $singleWidth * $item['facings'];
            $newSubcatWidth = $currentSubcatWidth + $singleWidth;

            if (($newSubcatWidth / $available) * 100 > $slot->max_share_per_subcategory) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns indices of $placedItems ordered by expansion priority.
     *
     * @param  array<int, array{product: mixed, facings: int, singleWidth: float, ordering: int}>  $placedItems
     * @return list<int>
     */
    private function expansionOrder(array $placedItems, FacingExpansion $mode): array
    {
        $indices = array_keys($placedItems);

        if ($mode === FacingExpansion::CurrentStock) {
            usort($indices, fn (int $a, int $b): int => (float) ($placedItems[$b]['product']->current_stock ?? 0) <=> (float) ($placedItems[$a]['product']->current_stock ?? 0));
        } elseif ($mode === FacingExpansion::TargetStock) {
            // Maior déficit (target - current) primeiro; quem não tem target vai para o fim
            usort($indices, function (int $a, int $b) use ($placedItems): int {
                $idA = $placedItems[$a]['product']->id;
                $idB = $placedItems[$b]['product']->id;
                $targetA = $this->targetStockMap[$idA] ?? null;
                $targetB = $this->targetStockMap[$idB] ?? null;

                if ($targetA === null && $targetB === null) {
                    return 0;
                }
                if ($targetA === null) {
                    return 1;
                }
                if ($targetB === null) {
                    return -1;
                }

                $deficitA = $targetA - (float) ($placedItems[$a]['product']->current_stock ?? 0);
                $deficitB = $targetB - (float) ($placedItems[$b]['product']->current_stock ?? 0);

                return $deficitB <=> $deficitA;
            });
        }

        // Score and Equal use existing order (products are already sorted by score)
        return $indices;
    }

    /**
     * @param  Collection<int, array{product: mixed, reason: PlacementFailureReason}>  $rejected
     * @return array{placed: Collection<int, PlacedSegment>, remaining: Collection<int, array{product: mixed, reason: PlacementFailureReason}>}
     */
    private function applyFallback(
        Collection $rejected,
        float $remainingWidth,
        PlanogramTemplateSlot $slot,
        Section $section,
        Shelf $shelf,
        int $orderingOffset,
    ): array {
        $placed = collect();
        $remaining = $rejected;

        if ($slot->space_fallback?->value === 'reduce_facings') {
            $occupied = 0.0;
            $ordering = $orderingOffset;
            $stillRejected = collect();

            foreach ($rejected as $item) {
                $product = $item['product'];
                $width = (int) round($this->widthResolver->resolve($product));

                if ($occupied + $width <= $remainingWidth) {
                    $placed->push(new PlacedSegment(
                        sectionId: $section->getKey(),
                        shelfId: $shelf->getKey(),
                        ordering: $ordering++,
                        position: (int) round($occupied),
                        width: $width,
                        distributedWidth: $width,
                        layers: collect([
                            new PlacedLayer(
                                productId: $product->id,
                                ean: (string) ($product->ean ?? ''),
                                quantity: 1,
                                height: 1,
                            ),
                        ]),
                    ));
                    $occupied += $width;
                } else {
                    $stillRejected->push($item);
                }
            }

            $remaining = $stillRejected;
        }

        // reduce_c and skip: do not attempt re-placement
        return ['placed' => $placed, 'remaining' => $remaining];
    }

    private function getShelfAvailableWidth(Section $section): float
    {
        $sectionWidth = (float) ($section->width ?? 100.0);
        $cremalheiraWidth = (float) ($section->cremalheira_width ?? 0.0);

        return max(0.0, $sectionWidth - $cremalheiraWidth);
    }

    private function recordSubtemplateUsed(string $planogramId, string $subtemplateId): void
    {
        Planogram::withoutGlobalScopes()->where('id', $planogramId)->update(['subtemplate_id' => $subtemplateId]);
    }

    /**
     * Resolve a zona térmica de uma prateleira para anotação de explicação.
     */
    private function resolveZoneForShelf(Section $section, Shelf $shelf): string
    {
        $numShelves = $section->shelves->count();

        if ($numShelves === 0) {
            return 'neutral';
        }

        return ShelfZoneResolver::resolve($this->shelfSortedIndex($section, $shelf), $numShelves);
    }

    /**
     * Índice ordenado da prateleira na seção (0 = topo).
     *
     * shelf_position no banco é coordenada em cm a partir do topo (0, 60, 120…) —
     * não pode ser passada direto ao ShelfZoneResolver, que espera índice 0..N-1.
     * Ordenar por shelf_position e usar a posição na lista funciona para ambas
     * as semânticas (coordenada em cm ou índice legado).
     */
    private function shelfSortedIndex(Section $section, Shelf $shelf): int
    {
        $index = $section->shelves
            ->sortBy('shelf_position')
            ->values()
            ->search(fn (Shelf $s): bool => $s->getKey() === $shelf->getKey());

        return $index === false ? 0 : (int) $index;
    }

    /**
     * Constrói a lista de explicações para produtos posicionados numa prateleira.
     * Chamado após expandFacings para refletir o número final de frentes.
     *
     * @param  array<int, array{product: mixed, facings: int, singleWidth: float, ordering: int}>  $placedItems
     * @return list<array<string, mixed>>
     */
    private function buildPlacedExplanations(
        array $placedItems,
        PlanogramTemplateSlot $slot,
        int $minFacing,
        string $zone,
        Shelf $shelf,
    ): array {
        $explanations = [];

        foreach ($placedItems as $item) {
            $p = $item['product'];

            // Estoque alvo "atendido" = produto atingiu (ou superou) o teto de frentes derivado
            // do alvo. Só faz sentido quando o slot usa estoque alvo; caso contrário fica false.
            $targetStockMet = false;

            if ($slot->use_target_stock) {
                $cap = $this->targetStockFacingCap($p, $shelf, $minFacing);
                $targetStockMet = $cap !== null && $item['facings'] >= $cap;
            }

            $explanations[] = [
                'product_id' => $p->id,
                'product_name' => $p->name ?? '',
                'slot_id' => $slot->id ?? null,
                'category_name' => $slot->category?->name ?? $slot->category_id,
                'abc_class' => $this->abcClassMap[$p->id] ?? null,
                'is_mandatory' => isset($this->mandatoryProductIds[$p->id]),
                'facings' => $item['facings'],
                'facings_expanded' => $item['facings'] > $minFacing,
                'zone' => $zone,
                'role' => $slot->effectiveRole()?->value,
                'has_target_stock' => isset($this->targetStockMap[$p->id]),
                'target_stock_met' => $targetStockMet,
            ];
        }

        return $explanations;
    }

    /**
     * Consolida explicações e alertas da geração completa.
     *
     * @param  list<array<string, mixed>>  $allPlacedExplanations
     * @param  Collection<int, array{product: mixed, reason: PlacementFailureReason, slot_id?: string}>  $rejected
     * @param  list<array<string, mixed>>  $slotAnalysis
     * @return array{allocated: list<array<string, mixed>>, rejected: list<array<string, mixed>>, alerts: list<array<string, mixed>>}
     */
    private function buildExplanationReport(
        array $allPlacedExplanations,
        Collection $rejected,
        array $slotAnalysis,
    ): array {
        $slotCategoryMap = collect($slotAnalysis)->keyBy('slot_id')->map(fn ($s) => $s['category_name'])->all();

        $rejectedExplanations = $rejected
            ->filter(fn ($r) => $r['product'] !== null)
            ->map(fn ($r) => [
                'product_id' => $r['product']->id,
                'product_name' => $r['product']->name ?? '',
                'slot_id' => $r['slot_id'] ?? null,
                'category_name' => isset($r['slot_id']) ? ($slotCategoryMap[$r['slot_id']] ?? null) : null,
                'abc_class' => $this->abcClassMap[$r['product']->id] ?? null,
                'motivo' => $r['reason']->value,
                'motivo_label' => $r['reason']->label(),
            ])
            ->values()
            ->all();

        $alerts = [];

        $missingDimCount = $rejected
            ->filter(fn ($r) => $r['reason'] === PlacementFailureReason::MissingDimensions && $r['product'] !== null)
            ->count();

        if ($missingDimCount > 0) {
            $alerts[] = [
                'type' => 'missing_dimensions',
                'count' => $missingDimCount,
                'message' => "{$missingDimCount} produto(s) sem dimensões (width/height) cadastradas",
            ];
        }

        $noSpaceCount = $rejected
            ->filter(fn ($r) => in_array($r['reason'], [
                PlacementFailureReason::NoHorizontalSpace,
                PlacementFailureReason::MandatoryNoSpace,
            ]) && $r['product'] !== null)
            ->count();

        if ($noSpaceCount > 0) {
            $alerts[] = [
                'type' => 'mix_excede_gondola',
                'count' => $noSpaceCount,
                'message' => "{$noSpaceCount} produto(s) não couberam na gôndola por falta de espaço",
            ];
        }

        // Déficit real de estoque alvo: tem alvo, não expandiu frentes E não atingiu o teto do
        // alvo (faltou espaço). Produtos que pararam por já cobrirem o alvo não contam.
        $targetNotMet = collect($allPlacedExplanations)
            ->filter(fn ($e) => $e['has_target_stock'] && ! $e['facings_expanded'] && ! ($e['target_stock_met'] ?? false))
            ->count();

        if ($targetNotMet > 0) {
            $alerts[] = [
                'type' => 'target_stock_not_met',
                'count' => $targetNotMet,
                'message' => "{$targetNotMet} produto(s) com estoque alvo definido não tiveram frentes expandidas",
            ];
        }

        return [
            'allocated' => $allPlacedExplanations,
            'rejected' => $rejectedExplanations,
            'alerts' => $alerts,
        ];
    }
}
