<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement;

use App\Models\Category;
use App\Models\Planogram;
use App\Models\Scopes\TenantScope;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\OrderedBlock;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PackCandidate;
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

    /** @var array<string, string|null> Cache do pai por category_id — escopo `siblings` do overflow */
    private array $parentCategoryCache = [];

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
        private readonly ShelfKnapsackPacker $packer = new ShelfKnapsackPacker,
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

        /*
         * Quantos slots do caminho horizontal cada categoria AINDA tem pela frente.
         *
         * O plano de slots dá N prateleiras a uma categoria dimensionando pela largura total
         * dos produtos dela (com as frentes já expandidas). Mas o motor coloca cada produto
         * com a frente MÍNIMA — e aí o sortimento inteiro cabe na primeira prateleira. O que
         * acontecia: o 1º slot levava TUDO e os N−1 seguintes não achavam mais nenhum produto
         * (`slots_sem_matching`), ficando VAZIOS. Numa gôndola medida, 7 de 16 prateleiras
         * zeradas — 39,7% de ocupação com o mix inteiro cabendo.
         *
         * Com este contador cada slot leva só a fatia dele (ver takeCategoryShare) e a expansão
         * de frentes engorda a fatia até encher a prateleira.
         *
         * A blocagem vertical fica de fora: ela já distribui a categoria pelas prateleiras do
         * grupo por conta própria.
         *
         * @var array<string, int>
         */
        $pendingSlotsByCategory = [];

        foreach ($slots as $s) {
            if (isset($verticalProcessedSlotIds[$s->id]) || $s->category_id === null) {
                continue;
            }

            $pendingSlotsByCategory[$s->category_id] = ($pendingSlotsByCategory[$s->category_id] ?? 0) + 1;
        }

        foreach ($slots as $slot) {
            // Slots já processados pela blocagem vertical não passam pelo caminho horizontal
            if (isset($verticalProcessedSlotIds[$slot->id])) {
                continue;
            }

            $this->applySlotOverride($slot);

            // Consome a cota da categoria já aqui: um slot que acabe sem produto (sem prateleira,
            // sem candidato) não pode continuar "reservando" fatia para si e encolher a dos outros.
            $pendingSlots = $pendingSlotsByCategory[$slot->category_id] ?? 1;

            if ($slot->category_id !== null) {
                $pendingSlotsByCategory[$slot->category_id] = max(0, $pendingSlots - 1);
            }

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

                /*
                 * O slot vazio PRECISA entrar na análise, com 0% de uso.
                 *
                 * Antes ele era pulado — e a ocupação média do relatório era a média só dos
                 * slots que receberam produto. Numa gôndola real com 7 de 16 prateleiras
                 * ZERADAS, o relatório anunciava 78% de ocupação enquanto a gôndola estava em
                 * 39,7%. A métrica escondia justamente o defeito que ela deveria denunciar.
                 */
                $slotAnalysis[] = [
                    'slot_id' => $slot->id,
                    'category_id' => $slot->category_id,
                    'category_name' => $slot->category?->name ?? $slot->category_id,
                    'role' => $slot->effectiveRole()?->value,
                    'module_number' => $slot->module_number,
                    'shelf_order' => $slot->shelf_order,
                    'shelf_id' => $shelf->getKey(),
                    'largura_total' => round($this->getShelfAvailableWidth($section), 1),
                    'largura_usada' => 0.0,
                    'largura_livre' => round($this->getShelfAvailableWidth($section), 1),
                    'percentual_uso' => 0,
                    'produtos_posicionados' => 0,
                    'produtos_rejeitados' => 0,
                    'rejeitados_sem_dimensao' => 0,
                    'produtos_rejeitados_nomes' => [],
                ];

                continue;
            }

            $ordered = $this->takeCategoryShare(
                $this->applySpaceFallbackOrdering(
                    $this->orderCandidates($candidates, $slot, $section, $shelf),
                    $slot,
                ),
                $slot,
                $pendingSlots,
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
        // prateleiras da MESMA categoria que ainda tenham espaço disponível, usando 1 frente.
        // Resolve o caso onde a categoria ocupa várias prateleiras e um produto rejeitado num
        // slot pode caber no espaço ocioso de outro slot da mesma categoria.
        //
        // Restrição por categoria: o overflow NÃO mistura categorias. Um produto só é realocado
        // em prateleiras cujos slots pertençam à sua categoria (ou descendente). Categorias
        // homônimas (mesmo nome, category_id diferente) são tratadas como distintas, por ID.
        $allowedCategoriesByShelf = $this->buildAllowedCategoriesByShelf($slots, $sections);
        [$placed, $rejected] = $this->placeOverflow($placed, $rejected, $sections, $allowedCategoriesByShelf);

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
     * prateleiras da MESMA categoria com espaço — ou em prateleiras inteiramente vazias, que
     * passam a ser dedicadas à categoria que transbordou.
     *
     * Lógica:
     * 1. Identifica produtos únicos rejeitados por espaço que ainda não foram posicionados.
     * 2. Calcula o espaço ocupado por prateleira a partir dos segmentos já posicionados.
     * 3. Ordena prateleiras por maior espaço disponível (prioriza as mais vazias).
     * 4. Posiciona os produtos ordenados por ABC (A→B→C). Uma prateleira é elegível quando:
     *    seu slot é da mesma categoria do produto, OU está inteiramente vazia — neste caso a
     *    prateleira é "reivindicada" e fica dedicada a essa categoria (uma categoria por
     *    prateleira, sem misturar). Isso aproveita prateleiras sobrando para dar mais espaço
     *    à mesma categoria em vez de rejeitar produtos.
     * 5. Cada produto entra com a frente mínima e, se tiver estoque alvo e ainda houver espaço
     *    na prateleira de destino, expande as frentes até o teto que cobre o alvo
     *    (targetStockFacingCap) — paridade com o caminho principal de expansão.
     * 6. Retorna as coleções atualizadas de placed e rejected.
     *
     * @param  Collection<int, PlacedSegment>  $placed
     * @param  Collection<int, array>  $rejected
     * @param  Collection<int, Section>  $sections
     * @param  array<string, array<string, true>>  $allowedCategoriesByShelf  [shelfId => set de category_id permitidos]
     * @return array{0: Collection<int, PlacedSegment>, 1: Collection<int, array>}
     */
    private function placeOverflow(
        Collection $placed,
        Collection $rejected,
        Collection $sections,
        array $allowedCategoriesByShelf = [],
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
                        'allowed_categories' => $allowedCategoriesByShelf[$shelf->getKey()] ?? [],
                        // Prateleira sem nenhum segmento posicionado: pode ser dedicada inteira a uma
                        // categoria que transbordou (sem misturar — uma categoria por prateleira).
                        'is_empty' => $occupied <= 0.01,
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

        // Prateleiras vazias já reivindicadas por uma categoria nesta passada de overflow
        // (shelfId => category_id). Garante "uma categoria por prateleira": depois que uma
        // categoria ocupa uma prateleira vazia, só produtos dela continuam ali.
        $claimedEmptyShelves = [];

        // Registros de colocação antes de construir os segmentos. O overflow roda em duas fases:
        //  A) variedade — coloca TODOS os SKUs rejeitados com a frente mínima;
        //  B) profundidade — só então expande frentes até o estoque alvo com o espaço que sobrar.
        // Sem isso, a expansão do primeiro produto consumiria o espaço que caberia a outros
        // SKUs da mesma categoria, deixando-os rejeitados (variedade < profundidade — errado).
        /** @var list<array{product: mixed, shelf_key: string, section_key: string, single_width: float, facings: int, cap: int|null}> $placements */
        $placements = [];

        // === PASSE A: variedade — cada produto rejeitado entra com a frente mínima ===
        foreach ($retryOrdered as $item) {
            $product = $item['product'];
            // Largura EXATA: arredondar a largura UNITÁRIA antes de multiplicar pelas frentes
            // distorcia a conta (um produto de 3,4cm × 5 frentes "ocupava" 15cm em vez de 17cm),
            // fazendo produtos entrarem em prateleiras onde na verdade não cabiam.
            $singleWidth = $this->widthResolver->resolve($product);

            if ($singleWidth <= 0) {
                continue;
            }

            // Min-facings ABC: mesmo critério do placement principal (A→3, B→2, C→1).
            // O overflow não reduz abaixo do mínimo — produtos que não cabem com seus
            // facings mínimos em nenhuma prateleira permanecem rejeitados.
            $abcClass = $this->abcClassMap[$product->id] ?? '';
            $minFacings = SlotPlanBuilder::ABC_MIN_FACINGS[$abcClass] ?? SlotPlanBuilder::ABC_MIN_FACINGS[''];
            $widthWithFacings = $singleWidth * $minFacings;

            // Tenta a prateleira com maior espaço disponível
            foreach ($shelfMeta as $i => $meta) {
                if (! PlacementMath::fits(0.0, $widthWithFacings, $meta['remaining'])) {
                    continue;
                }

                $productCategoryId = $product->category_id ?? null;

                if ($productCategoryId === null) {
                    continue;
                }

                // Prateleira vazia já reivindicada por OUTRA categoria: bloqueada (não mistura).
                $shelfKey = $meta['shelf']->getKey();
                $claimedBy = $claimedEmptyShelves[$shelfKey] ?? null;

                if ($claimedBy !== null && $claimedBy !== $productCategoryId) {
                    continue;
                }

                // Elegibilidade:
                //  - prateleira cujo slot é da mesma categoria (ou descendente) do produto; OU
                //  - prateleira sobrando (vazia E sem nenhum slot designado), que passa a ser
                //    dedicada a esta categoria.
                // Só prateleiras realmente livres são reivindicadas — uma prateleira designada a
                // outra categoria (mesmo que vazia) é preservada para ela. Assim aproveitamos as
                // prateleiras que sobraram sem misturar categorias.
                $ownedBySlot = isset($meta['allowed_categories'][$productCategoryId]);
                $isLeftoverShelf = $meta['is_empty'] && $meta['allowed_categories'] === [];
                $claimableEmpty = $isLeftoverShelf || $claimedBy === $productCategoryId;

                if (! $ownedBySlot && ! $claimableEmpty) {
                    continue;
                }

                // Respeita o vão livre: não realoca produto em prateleira mais baixa que ele.
                // Clearance 0 = dado físico ausente → checagem desativada (legado).
                $productHeight = (float) ($product->height ?? 0);

                if ($meta['clearance'] > 0 && $productHeight > $meta['clearance']) {
                    continue;
                }

                // Coloca apenas com a frente mínima nesta fase (variedade primeiro). A expansão
                // por estoque alvo acontece no passe B, com o espaço remanescente.
                $placements[] = [
                    'product' => $product,
                    'shelf_key' => $shelfKey,
                    'section_key' => $meta['section']->getKey(),
                    'single_width' => $singleWidth,
                    'facings' => $minFacings,
                    'cap' => $this->targetStockFacingCap($product, $meta['shelf'], $minFacings),
                ];

                $shelfMeta[$i]['occupied'] += $widthWithFacings;
                $shelfMeta[$i]['remaining'] -= $widthWithFacings;
                $shelfMeta[$i]['is_empty'] = false;
                $this->globalPlacedProductIds[$product->id] = true;
                $overflowPlacedIds[$product->id] = true;

                // Reivindica a prateleira para esta categoria quando não era de um slot dela
                // (prateleira vazia dedicada): impede que outra categoria a ocupe depois.
                if (! $ownedBySlot) {
                    $claimedEmptyShelves[$shelfKey] = $productCategoryId;
                }

                // Re-ordena por espaço disponível após cada alocação (greedy)
                usort($shelfMeta, fn ($a, $b) => $b['remaining'] <=> $a['remaining']);

                break;
            }
        }

        if ($placements === []) {
            return [$placed, $rejected];
        }

        // === PASSE B: profundidade — expande frentes até o estoque alvo com o espaço que sobrou ===
        $remainingByShelf = [];
        foreach ($shelfMeta as $meta) {
            $remainingByShelf[$meta['shelf']->getKey()] = $meta['remaining'];
        }

        // Round-robin: +1 frente por vez enquanto houver espaço e o alvo não tiver sido atingido.
        // Distribui o espaço sobrante de forma equilibrada entre os produtos da prateleira.
        $changed = true;
        while ($changed) {
            $changed = false;

            foreach ($placements as $idx => $p) {
                if ($p['cap'] === null || $p['facings'] >= $p['cap']) {
                    continue;
                }

                if (($remainingByShelf[$p['shelf_key']] ?? 0.0) < $p['single_width']) {
                    continue;
                }

                $placements[$idx]['facings']++;
                $remainingByShelf[$p['shelf_key']] -= $p['single_width'];
                $changed = true;
            }
        }

        // === Constrói os segmentos com as frentes finais, em sequência por prateleira ===
        $shelfCursor = [];
        foreach ($placements as $p) {
            $shelfKey = $p['shelf_key'];
            $start = $shelfCursor[$shelfKey] ?? (float) $occupiedPerShelf->get($shelfKey, 0.0);
            $exactWidth = $p['single_width'] * $p['facings'];

            // Mesma soma de prefixos do placement principal: arredonda os PONTOS (início/fim),
            // não cada largura isolada — segmentos contíguos, sem erro acumulado.
            [$startCm, $width] = PlacementMath::segmentBounds($start, $exactWidth);

            $overflowPlaced->push(new PlacedSegment(
                sectionId: $p['section_key'],
                shelfId: $shelfKey,
                ordering: $orderingOffset++,
                position: $startCm,
                width: $width,
                distributedWidth: $width,
                layers: collect([
                    new PlacedLayer(
                        productId: $p['product']->id,
                        ean: (string) ($p['product']->ean ?? ''),
                        quantity: $p['facings'],
                        height: 1,
                    ),
                ]),
            ));

            $shelfCursor[$shelfKey] = $start + $exactWidth;
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

    /**
     * Mapeia cada prateleira física para o conjunto de category_ids que ela aceita no overflow.
     *
     * Uma prateleira pode ter slots de múltiplas categorias (prateleira compartilhada), por isso
     * o valor é um set. Cada slot contribui com sua categoria E todos os descendentes — o
     * matching do placement principal é hierárquico, e o overflow precisa ser consistente.
     *
     * ── Até onde o produto rejeitado pode andar (config `overflow_scope`) ────────────────────
     * Medido numa gôndola real: 257cm de prateleira VAZIA convivendo com 11 produtos rejeitados
     * por falta de espaço. Não faltava espaço — faltava PERMISSÃO. A categoria que não tinha
     * produto para encher a prateleira dela segurava o espaço, e a categoria que transbordava
     * não podia usá-lo.
     *
     *   strict   — só a própria categoria (e descendentes). Blocagem intacta, gôndola aberta.
     *   siblings — também as categorias IRMÃS (mesmo pai no mercadológico). PADRÃO: fecha a
     *              maior parte do vão sem virar bagunça na gaveta, porque irmãs já ficam juntas
     *              na loja (LÍQUIDO ao lado de GEL, ambas filhas de CUIDADO COM O BANHEIRO).
     *   any      — qualquer categoria do planograma. Fecha tudo, mas quebra a blocagem.
     *
     * @param  Collection<int, PlanogramTemplateSlot>  $slots
     * @param  Collection<int, Section>  $sections
     * @return array<string, array<string, true>> [shelfId => [category_id => true]]
     */
    private function buildAllowedCategoriesByShelf(Collection $slots, Collection $sections): array
    {
        $scope = (string) config('plannerate.auto_planogram.placement.overflow_scope', 'siblings');
        $map = [];

        // `any`: o conjunto permitido é o mesmo em toda prateleira — a união das categorias de
        // TODOS os slots. Calculado uma vez só, em vez de por prateleira.
        $everyCategory = [];

        if ($scope === 'any') {
            foreach ($slots as $slot) {
                if ($slot->category_id === null) {
                    continue;
                }

                foreach ($this->getDescendantsCached($slot->category_id) as $categoryId) {
                    $everyCategory[$categoryId] = true;
                }
            }
        }

        foreach ($slots as $slot) {
            if ($slot->category_id === null) {
                continue;
            }

            $section = $this->resolveSection($sections, $slot->module_number);
            $shelf = $section ? $this->resolveShelf($section, $slot->shelf_order) : null;

            if ($shelf === null) {
                continue;
            }

            $shelfId = $shelf->getKey();

            if ($scope === 'any') {
                $map[$shelfId] = ($map[$shelfId] ?? []) + $everyCategory;

                continue;
            }

            // `siblings`: descer a partir do PAI da categoria do slot alcança as irmãs (e os
            // descendentes delas). Sem pai — categoria raiz — não há irmãs: cai no strict.
            $scopeCategoryId = $slot->category_id;

            if ($scope === 'siblings') {
                $scopeCategoryId = $this->parentCategoryIdCached($slot->category_id) ?? $slot->category_id;
            }

            foreach ($this->getDescendantsCached($scopeCategoryId) as $categoryId) {
                $map[$shelfId][$categoryId] = true;
            }
        }

        return $map;
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

    /**
     * Pai de uma categoria no mercadológico, cacheado por geração.
     *
     * A árvore é auto-referenciada pela coluna `category_id` (não `parent_id`) — mesma coluna
     * que Category::getDescendantIds() percorre para descer. Sem o TenantScope, pelo mesmo
     * motivo que lá: a árvore é lida no contexto do tenant já corrente.
     *
     * `null` quando a categoria é raiz (não tem irmãs) ou não foi encontrada. Usado pelo
     * escopo `siblings` do overflow — ver buildAllowedCategoriesByShelf.
     */
    private function parentCategoryIdCached(string $categoryId): ?string
    {
        if (! array_key_exists($categoryId, $this->parentCategoryCache)) {
            $this->parentCategoryCache[$categoryId] = Category::withoutGlobalScope(TenantScope::class)
                ->whereKey($categoryId)
                ->value('category_id');
        }

        return $this->parentCategoryCache[$categoryId];
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
        $spacing = PlacementMath::productSpacingCm();

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

            // Largura EXATA (float): arredondar aqui para cm inteiro rejeitava produtos
            // que cabiam por fração de cm e deixava sobra acumulada ao longo da prateleira.
            // O arredondamento acontece só na persistência (ver soma de prefixos abaixo).
            $width = $singleWidth * $facing;

            // Folga entre produtos (0 por padrão) — cobrada só a partir do segundo produto.
            $gap = PlacementMath::gapBefore($occupied, $spacing);

            if (PlacementMath::fits($occupied + $gap, $width, $available)) {
                $placedItems[] = [
                    'product' => $product,
                    'facings' => $facing,
                    'singleWidth' => $singleWidth,
                    'ordering' => $ordering++,
                ];
                $occupied += $gap + $width;
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

        // Rejeitados por ESPAÇO voltam a concorrer (no empacotador e no fallback); rejeitados
        // por física — sem dimensão, mais alto que o vão — são definitivos.
        $noSpaceRejected = $rejected->where('reason', PlacementFailureReason::NoHorizontalSpace)->values();
        $otherRejected = $rejected
            ->filter(fn (array $r): bool => $r['reason'] !== PlacementFailureReason::NoHorizontalSpace)
            ->values();

        // Phase 2: empacotador exato — reabre a decisão da prateleira INTEIRA, com as frentes
        // como variável livre e os rejeitados por espaço de volta ao jogo. Tudo que o first-fit
        // acima já colocou entra como obrigatório, então isto nunca perde um SKU que o motor
        // antigo colocaria: só pode fechar o vão que ele deixava aberto.
        if ($this->packerEnabled()) {
            $packed = $this->packShelf($products, $placedItems, $noSpaceRejected, $slot, $shelf, $available);

            if ($packed !== null) {
                $placedItems = $packed['items'];
                $noSpaceRejected = $packed['rejected'];
                $occupied = $packed['occupied'];
            }
        }

        // Phase 3: expande as frentes com o vão que ainda sobrar. Depois do empacotador sobra
        // pouco — só a folga do arredondamento em mm —, mas é ela que fecha o último centímetro.
        if ($slot->facing_expansion !== FacingExpansion::None && $placedItems !== []) {
            [$placedItems, $occupied] = $this->expandFacings($placedItems, $slot, $available, $occupied, $shelf);
        }

        // Phase 4: fallback "reduzir frentes" — quem ficou de fora entra com 1 frente no resto.
        if ($noSpaceRejected->isNotEmpty() && $slot->space_fallback === SpaceFallback::ReduceFacings) {
            $fallback = $this->applyReduceFacingsFallback($noSpaceRejected, $placedItems, $available, $occupied);
            $placedItems = $fallback['items'];
            $noSpaceRejected = $fallback['remaining'];
            $occupied = $fallback['occupied'];
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
        $isFirst = true;

        foreach ($placedItems as $item) {
            $product = $item['product'];
            $facings = $item['facings'];
            $exactWidth = $item['singleWidth'] * $facings;

            // Folga entre produtos: avança o cursor antes de posicionar (nunca antes do 1º).
            if (! $isFirst) {
                $x += $spacing;
            }

            $isFirst = false;

            // As colunas segments.position/width são inteiras (em cm), mas arredondar cada
            // largura isoladamente e somá-las acumula erro (até 0,5cm por segmento) e faz a
            // prateleira "andar". Arredondando os PONTOS (início/fim) da posição exata, os
            // segmentos ficam contíguos por construção — sem gaps nem sobreposição — e o
            // total continua fiel à largura real ocupada.
            [$startCm, $width] = PlacementMath::segmentBounds($x, $exactWidth);

            $placed->push(new PlacedSegment(
                sectionId: $section->getKey(),
                shelfId: $shelf->getKey(),
                ordering: $item['ordering'],
                position: $startCm,
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

            $x += $exactWidth;
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

        return [
            'placed' => $placed,
            'rejected' => $noSpaceRejected->merge($otherRejected),
            'placed_explanations' => $placedExplanations,
        ];
    }

    /**
     * O empacotador exato está ligado?
     *
     * Interruptor de segurança: `greedy` restaura o motor antigo inteiro (first-fit + round-robin)
     * sem precisar de deploy, caso alguma gôndola real saia pior do que a versão anterior.
     */
    private function packerEnabled(): bool
    {
        return config('plannerate.auto_planogram.placement.packer', 'knapsack') === 'knapsack';
    }

    /**
     * Fase 2 — resolve a prateleira com o empacotador exato (bounded knapsack).
     *
     * Traduz a regra de negócio (ranking, ABC, estoque alvo, limites do slot) em números que o
     * ShelfKnapsackPacker entende, resolve, e traduz a resposta de volta em itens posicionáveis.
     *
     * Os produtos que o first-fit já colocou entram como OBRIGATÓRIOS — daí a garantia de que o
     * resultado nunca é pior. Os rejeitados por falta de espaço entram como opcionais: é assim
     * que um produto estreito que foi descartado cedo volta a tapar o vão que sobrou no fim.
     *
     * @param  Collection<int, mixed>  $products  Pool na ordem de ranking (define a prioridade).
     * @param  array<int, array{product: mixed, facings: int, singleWidth: float, ordering: int}>  $placedItems
     * @param  Collection<int, array{product: mixed, reason: PlacementFailureReason, slot_id?: string}>  $noSpaceRejected
     * @return array{items: array<int, array{product: mixed, facings: int, singleWidth: float, ordering: int}>, rejected: Collection<int, array>, occupied: float}|null
     */
    private function packShelf(
        Collection $products,
        array $placedItems,
        Collection $noSpaceRejected,
        PlanogramTemplateSlot $slot,
        Shelf $shelf,
        float $available,
    ): ?array {
        if ($placedItems === [] && $noSpaceRejected->isEmpty()) {
            return null;
        }

        $spacing = PlacementMath::productSpacingCm();
        $minFacings = max($slot->min_facings, 1);
        $maxFacings = max($slot->max_facings, 1);
        $expansion = $slot->facing_expansion ?? FacingExpansion::Score;

        /*
         * Frentes congeladas no mínimo (o DP só decide QUEM entra) quando:
         *  - o slot não quer expansão nenhuma; ou
         *  - há limite de participação por MARCA ou SUBCATEGORIA. Esses limites são agregados
         *    (dependem das frentes dos outros produtos), e uma mochila só sabe lidar com custo
         *    por item — não dá para representá-los sem mentir. Nesse caso a profundidade fica
         *    com o expandFacings, que os checa de verdade a cada frente.
         * O limite por SKU não entra aqui: esse é por item, e vira teto de frentes lá embaixo.
         */
        $freezeFacings = $expansion === FacingExpansion::None
            || ($slot->max_share_per_brand !== null && $slot->max_share_per_brand > 0)
            || ($slot->max_share_per_subcategory !== null && $slot->max_share_per_subcategory > 0);

        // A posição no pool ordenado É a prioridade do produto — o empacotador a recebe como
        // valor de inclusão, para que ao disputar o mesmo vão vença o mais bem ranqueado.
        $rankById = [];

        foreach ($products as $rank => $product) {
            $rankById[$product->id] = $rank;
        }

        /** @var list<array{product: mixed, width: float, forced: bool, rank: int}> $entries */
        $entries = [];

        foreach ($placedItems as $item) {
            $entries[] = [
                'product' => $item['product'],
                'width' => $item['singleWidth'],
                'forced' => true,
                'rank' => $rankById[$item['product']->id] ?? PHP_INT_MAX,
            ];
        }

        foreach ($noSpaceRejected as $rejection) {
            $product = $rejection['product'];

            if ($product === null) {
                continue;
            }

            $entries[] = [
                'product' => $product,
                'width' => $this->widthResolver->resolve($product),
                'forced' => false,
                'rank' => $rankById[$product->id] ?? PHP_INT_MAX,
            ];
        }

        // Ordem de ranking: define tanto a prioridade no DP quanto o X final na prateleira.
        usort($entries, fn (array $a, array $b): int => $a['rank'] <=> $b['rank']);

        $total = count($entries);
        $facingWeights = $this->facingWeights($entries, $expansion);
        $candidates = [];

        foreach ($entries as $position => $entry) {
            $width = $entry['width'];

            if ($width <= 0) {
                return null;
            }

            /*
             * `reduce_facings` significa exatamente "quem não coube entra com 1 frente" — no
             * empacotador isso é só um piso menor para os opcionais, e sai de graça: o DP já
             * escolhe a melhor combinação entre entrar com 1 frente ou não entrar.
             */
            $entryMin = (! $entry['forced'] && $slot->space_fallback === SpaceFallback::ReduceFacings)
                ? 1
                : $minFacings;

            $entryMax = $freezeFacings
                ? $entryMin
                : max($entryMin, $this->facingCeiling($entry['product'], $slot, $shelf, $maxFacings, $minFacings, $width, $available));

            $candidates[] = new PackCandidate(
                singleWidth: $width,
                minFacings: $entryMin,
                maxFacings: $entryMax,
                // (0,5 … 1,0]: o piso de 0,5 garante que incluir QUALQUER SKU ainda vale mais
                // que qualquer quantidade de frentes extras (ver INCLUSION_WEIGHT no packer).
                inclusionScore: 0.5 + 0.5 * (($total - $position) / $total),
                facingWeight: $facingWeights[$position],
                forced: $entry['forced'],
            );
        }

        $solution = $this->packer->pack($candidates, $available, $spacing);

        if ($solution === null) {
            return null;
        }

        $items = [];
        $stillRejected = collect();
        $occupied = 0.0;
        $ordering = 0;

        foreach ($entries as $position => $entry) {
            $facings = $solution[$position] ?? 0;

            if ($facings <= 0) {
                $stillRejected->push([
                    'product' => $entry['product'],
                    'reason' => PlacementFailureReason::NoHorizontalSpace,
                    'slot_id' => $slot->id,
                ]);

                continue;
            }

            $occupied += PlacementMath::gapBefore($occupied, $spacing) + $entry['width'] * $facings;

            $items[] = [
                'product' => $entry['product'],
                'facings' => $facings,
                'singleWidth' => $entry['width'],
                'ordering' => $ordering++,
            ];
        }

        return ['items' => $items, 'rejected' => $stillRejected, 'occupied' => $occupied];
    }

    /**
     * Teto de frentes de um produto, consolidando todos os limites que são POR ITEM.
     *
     * Fica de fora o limite por marca/subcategoria, que é agregado — ver `$freezeFacings`.
     */
    private function facingCeiling(
        mixed $product,
        PlanogramTemplateSlot $slot,
        Shelf $shelf,
        int $maxFacings,
        int $minFacings,
        float $singleWidth,
        float $available,
    ): int {
        $ceiling = $maxFacings;

        if ($slot->use_target_stock) {
            $cap = $this->targetStockFacingCap($product, $shelf, $minFacings);

            if ($cap !== null) {
                $ceiling = min($ceiling, $cap);
            }
        }

        // Participação máxima de um SKU no slot: vira teto de frentes direto (limite por item).
        if ($slot->max_share_per_sku !== null && $slot->max_share_per_sku > 0) {
            $maxWidth = $available * $slot->max_share_per_sku / 100;
            $ceiling = min($ceiling, max(1, (int) floor(($maxWidth + PlacementMath::WIDTH_EPSILON_CM) / $singleWidth)));
        }

        return $ceiling;
    }

    /**
     * Peso de cada frente extra por produto, traduzindo o `facing_expansion` do slot.
     *
     * Normalizado em (0, 1] — o valor absoluto não importa, só a proporção entre produtos.
     * O piso de 0,05 evita que um produto com métrica zerada fique permanentemente sem
     * poder receber frentes (ele ainda perde a disputa, mas não é excluído por construção).
     *
     * @param  list<array{product: mixed, width: float, forced: bool, rank: int}>  $entries
     * @return list<float>
     */
    private function facingWeights(array $entries, FacingExpansion $expansion): array
    {
        $total = count($entries);
        $raw = [];

        foreach ($entries as $position => $entry) {
            $product = $entry['product'];

            $raw[$position] = match ($expansion) {
                FacingExpansion::CurrentStock => (float) ($product->current_stock ?? 0),
                FacingExpansion::TargetStock => max(
                    0.0,
                    ($this->targetStockMap[$product->id] ?? 0.0) - (float) ($product->current_stock ?? 0),
                ),
                FacingExpansion::Equal => 1.0,
                // Score (e None, onde as frentes ficam congeladas de todo jeito): a própria
                // posição no ranking — o mais bem colocado ganha as frentes extras primeiro.
                default => ($total - $position) / $total,
            };
        }

        $max = max($raw);

        if ($max <= 0.0) {
            return array_fill(0, $total, 1.0);
        }

        return array_map(fn (float $value): float => max(0.05, $value / $max), $raw);
    }

    /**
     * Reparte o sortimento da categoria entre os slots que ela ainda tem pela frente.
     *
     * ── O bug que isto conserta ──────────────────────────────────────────────────────────
     * O `SlotPlanBuilder` dá N prateleiras a uma categoria dimensionando pela largura TOTAL
     * dos produtos dela. Só que o posicionamento coloca cada produto com a frente MÍNIMA — e
     * com 1 frente o sortimento inteiro costuma caber numa prateleira só.
     *
     * Resultado: o 1º slot da categoria levava TODOS os produtos, e do 2º em diante o
     * `findCandidates` não achava mais nada (os produtos já estavam em `globalPlacedProductIds`).
     * Os slots irmãos ficavam VAZIOS — é o `slots_sem_matching` do log. A expansão de frentes
     * não salvava, porque ela só trabalha DENTRO da prateleira onde o produto já está: enchia
     * a primeira até 96-100% e não tinha como transbordar para as irmãs vazias ao lado.
     *
     * Medido numa gôndola real (mix cabendo inteiro na gôndola): 7 de 16 prateleiras ZERADAS,
     * 39,7% de ocupação, 672cm de prateleira morta.
     *
     * ── O conserto ───────────────────────────────────────────────────────────────────────
     * Cada slot leva a fatia dele: os melhores ranqueados ainda não usados, até cobrir 1/N da
     * largura que resta (mesma métrica de largura que o plano usou para pedir as N prateleiras).
     * O resto fica para os slots seguintes da categoria, e a expansão de frentes engorda cada
     * fatia até encher a sua prateleira.
     *
     * O último slot da categoria (`$pendingSlots === 1`) leva tudo o que sobrou — ninguém fica
     * para trás.
     *
     * @param  Collection<int, mixed>  $ordered  Candidatos já ranqueados para ESTE slot.
     * @param  int  $pendingSlots  Slots da categoria ainda por processar, incluindo este.
     * @return Collection<int, mixed>
     */
    private function takeCategoryShare(Collection $ordered, PlanogramTemplateSlot $slot, int $pendingSlots): Collection
    {
        if ($pendingSlots <= 1 || $ordered->count() <= 1) {
            return $ordered;
        }

        $totalWidth = (float) $ordered->sum(fn ($product): float => $this->widthResolver->resolve($product));

        if ($totalWidth <= 0.0) {
            return $ordered;
        }

        $targetWidth = $totalWidth / $pendingSlots;
        $share = collect();
        $accumulated = 0.0;

        foreach ($ordered as $product) {
            // Pelo menos um produto sempre entra: devolver fatia vazia deixaria a prateleira
            // zerada — exatamente o defeito que este método existe para eliminar.
            if ($share->isNotEmpty() && $accumulated >= $targetWidth) {
                break;
            }

            $share->push($product);
            $accumulated += $this->widthResolver->resolve($product);
        }

        Log::debug('TemplatePlacementEngine: sortimento repartido entre os slots da categoria', [
            'slot_id' => $slot->id,
            'category_id' => $slot->category_id,
            'slots_restantes' => $pendingSlots,
            'candidatos' => $ordered->count(),
            'fatia_deste_slot' => $share->count(),
            'largura_alvo_cm' => round($targetWidth, 1),
            'largura_da_fatia_cm' => round($accumulated, 1),
        ]);

        return $share->values();
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
                // Largura exata (float): ver WIDTH_EPSILON_CM e a soma de prefixos na escrita.
                $width = $singleWidth * $minFacings;

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
                        // Exato: arredondar por item subestimava/superestimava o ocupado e a
                        // expansão de frentes decidia em cima de um espaço livre errado.
                        $occupied += $item['singleWidth'] * $item['facings'];
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
                    $exactWidth = $item['singleWidth'] * $item['facings'];

                    // Soma de prefixos (ver placement principal): arredonda os pontos, não as larguras.
                    [$startCm, $width] = PlacementMath::segmentBounds($cellX, $exactWidth);

                    $placed->push(new PlacedSegment(
                        sectionId: $section->getKey(),
                        shelfId: $row['shelf']->getKey(),
                        ordering: 0, // reatribuído após o espelhamento
                        position: $startCm,
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
                    $cellX += $exactWidth;
                    $rowStats[$rowIdx]['placed']++;
                    $rowStats[$rowIdx]['occupied'] += $exactWidth;
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
     * Fallback `reduce_facings`: quem ficou de fora entra com 1 frente no vão que sobrou.
     *
     * Devolve ITENS (não segmentos) para entrar na mesma esteira dos demais: assim herdam o
     * cursor de X, a folga entre produtos e o espelhamento do fluxo direita→esquerda.
     * A versão anterior montava os segmentos por fora dessa esteira e os posicionava a partir
     * de x=0 — eles SOBREPUNHAM os produtos já colocados e ainda escapavam do espelhamento.
     *
     * Com o empacotador ligado este passe quase nunca encontra o que fazer: o DP já testou
     * incluir esses produtos com 1 frente (é o mesmo piso) e usou o vão. Ele continua aqui
     * para o caso de o empacotador estar desligado ou abortar.
     *
     * @param  Collection<int, array{product: mixed, reason: PlacementFailureReason, slot_id?: string}>  $rejected
     * @param  array<int, array{product: mixed, facings: int, singleWidth: float, ordering: int}>  $placedItems
     * @return array{items: array<int, array{product: mixed, facings: int, singleWidth: float, ordering: int}>, remaining: Collection<int, array>, occupied: float}
     */
    private function applyReduceFacingsFallback(
        Collection $rejected,
        array $placedItems,
        float $available,
        float $occupied,
    ): array {
        $spacing = PlacementMath::productSpacingCm();
        $ordering = count($placedItems);
        $stillRejected = collect();

        foreach ($rejected as $item) {
            $product = $item['product'];

            if ($product === null) {
                $stillRejected->push($item);

                continue;
            }

            $width = $this->widthResolver->resolve($product);
            $gap = PlacementMath::gapBefore($occupied, $spacing);

            if (! PlacementMath::fits($occupied + $gap, $width, $available)) {
                $stillRejected->push($item);

                continue;
            }

            $placedItems[] = [
                'product' => $product,
                'facings' => 1,
                'singleWidth' => $width,
                'ordering' => $ordering++,
            ];

            $occupied += $gap + $width;
        }

        return ['items' => $placedItems, 'remaining' => $stillRejected, 'occupied' => $occupied];
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
