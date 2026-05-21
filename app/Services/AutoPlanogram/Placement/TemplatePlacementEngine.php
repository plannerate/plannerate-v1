<?php

namespace App\Services\AutoPlanogram\Placement;

use App\Enums\BrandExposure;
use App\Enums\FacingExpansion;
use App\Enums\PlacementFailureReason;
use App\Enums\PriceOrder;
use App\Enums\SizeOrder;
use App\Enums\SpaceFallback;
use App\Enums\ZonePriority;
use App\Models\Category;
use App\Models\Planogram;
use App\Models\PlanogramSubtemplate;
use App\Models\PlanogramTemplateSlot;
use App\Models\Scopes\TenantScope;
use App\Services\AutoPlanogram\DTO\OrderedBlock;
use App\Services\AutoPlanogram\DTO\PlacedLayer;
use App\Services\AutoPlanogram\DTO\PlacedSegment;
use App\Services\AutoPlanogram\DTO\PlacementResult;
use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\ProductSizeResolver;
use App\Services\AutoPlanogram\ProductWidthResolver;
use App\Services\AutoPlanogram\ShelfZoneResolver;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Section;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Shelf;
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

    /** @var array<string, float> Mapa de estoque alvo [product_id => float] vindo de PlacementSettings */
    private array $targetStockMap = [];

    /** @var array<string, array{giro: float, margem: float}> Métricas por produto para ordenação por zona */
    private array $zoneMetricsMap = [];

    /** Critério de priorização para zona quente (Eye + Hand) */
    private ZonePriority $hotZonePriority = ZonePriority::None;

    /** Critério de priorização para zona fria (High + Low) */
    private ZonePriority $coldZonePriority = ZonePriority::None;

    public function __construct(
        private readonly ProductWidthResolver $widthResolver,
        private readonly ProductSizeResolver $sizeResolver,
        private readonly GreedyShelfPlacer $greedyPlacer,
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
        $this->targetStockMap = $settings->targetStockMap;
        $this->zoneMetricsMap = $settings->zoneMetricsMap;

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

        $placed = collect();
        $rejected = collect();
        $groupingsSemProduto = 0;
        $slotAnalysis = [];

        $slots = $subtemplate->slots()
            ->withoutGlobalScope(TenantScope::class)
            ->with('category')
            ->orderBy('module_number')
            ->orderBy('shelf_order')
            ->orderBy('ordering')
            ->get();

        foreach ($slots as $slot) {
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

            $candidates = $this->findCandidates($slot, $settings);

            if ($candidates->isEmpty()) {
                $groupingsSemProduto++;
                Log::debug('TemplatePlacementEngine: sem produto para slot', [
                    'category_id' => $slot->category_id,
                    'category_name' => $slot->relationLoaded('category') ? ($slot->category?->name ?? 'sem categoria') : 'não carregada',
                    'module' => $slot->module_number,
                    'shelf_order' => $slot->shelf_order,
                ]);

                continue;
            }

            $ordered = $this->orderCandidates($candidates, $slot, $section, $shelf);

            // ReduceC: garante que produtos C ficam por último para serem rejeitados primeiro
            if ($slot->space_fallback === SpaceFallback::ReduceC && ! empty($this->abcClassMap)) {
                $ordered = $ordered->sortBy(fn ($p) => match ($this->abcClassMap[$p->id] ?? 'B') {
                    'A' => 0,
                    'B' => 1,
                    'C' => 2,
                    default => 1,
                })->values();
            }

            $available = $this->getShelfAvailableWidth($section);
            $slotResult = $this->distributeInShelf($ordered, $section, $shelf, $slot, $available);

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

            $placed = $placed->merge($slotResult['placed']);
            $rejected = $rejected->merge($slotResult['rejected']);

            $occupied = round((float) $slotResult['placed']->sum('width'), 1);
            $livre = round(max(0.0, $available - $occupied), 1);
            $slotAnalysis[] = [
                'slot_id' => $slot->id,
                'category_id' => $slot->category_id,
                'category_name' => $slot->category?->name ?? $slot->category_id,
                'role' => $slot->effectiveRole()?->value,
                'module_number' => $slot->module_number,
                'shelf_order' => $slot->shelf_order,
                'shelf_id' => $shelf->getKey(),
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

        if ($settings->planogramId !== null) {
            $this->recordSubtemplateUsed($settings->planogramId, $subtemplate->getKey());
        }

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
            'rejeitados_sem_espaco' => $rejected->whereNotNull('product')->where('reason', PlacementFailureReason::NoHorizontalSpace)->count(),
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

        return new PlacementResult(
            placedSegments: $placed,
            rejectedProducts: $rejected,
            slotAnalysis: $slotAnalysis,
            modulesMismatch: $gondolaModules > $templateModules,
            templateModules: $templateModules,
            gondolaModules: $gondolaModules,
            subtemplateId: $subtemplate->getKey(),
        );
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
        $sorted = $slot->visual_criteria !== null
            ? $this->applyCriteriaCascade($products, $slot->visual_criteria)
            : $this->applyLegacyOrdering($products, $slot);

        // Zona térmica — aplicado por último para ser critério primário (stable sort)
        $sorted = $this->applyZoneOrdering($sorted, $section, $shelf);

        return $sorted->values();
    }

    /**
     * Comportamento legado: size → price → brand (para compatibilidade quando visual_criteria = null).
     */
    private function applyLegacyOrdering(Collection $products, PlanogramTemplateSlot $slot): Collection
    {
        $sorted = $products;

        if ($slot->size_order !== SizeOrder::None) {
            $sorted = $sorted->sortBy(
                fn ($p) => $this->sizeResolver->resolve($p),
                SORT_NUMERIC,
                $slot->size_order === SizeOrder::Desc,
            );
        }

        if ($slot->price_order !== PriceOrder::None && $products->first()?->price !== null) {
            $sorted = $sorted->sortBy(
                fn ($p) => (float) ($p->price ?? 0),
                SORT_NUMERIC,
                $slot->price_order === PriceOrder::Desc,
            );
        }

        if ($slot->brand_exposure === BrandExposure::Vertical) {
            $sorted = $sorted->groupBy(fn ($p) => $p->brand ?? 'SEM MARCA')->flatten(1);
        }

        return $sorted;
    }

    /**
     * Aplica ordenação estável em cascata pela lista de critérios.
     * Aplica do menos prioritário ao mais prioritário (ordem reversa),
     * para que o primeiro critério da lista domine o resultado final.
     *
     * @param  list<array{key: string, direction: string}>  $criteria
     */
    private function applyCriteriaCascade(Collection $products, array $criteria): Collection
    {
        $sorted = $products;

        foreach (array_reverse($criteria) as $item) {
            $key = $item['key'] ?? '';
            $direction = $item['direction'] ?? 'none';
            $sorted = $this->applySingleCriterion($sorted, $key, $direction);
        }

        return $sorted;
    }

    /**
     * Aplica um único critério de ordenação (stable sort).
     *
     * @param  string  $key  marca|preco|tamanho|score_abc|margem
     * @param  string  $direction  asc|desc|none
     */
    private function applySingleCriterion(Collection $products, string $key, string $direction): Collection
    {
        $desc = $direction === 'desc';

        return match ($key) {
            'marca' => $products->sortBy(
                fn ($p) => strtolower((string) ($p->brand ?? 'zzz')),
                SORT_STRING,
                $desc,
            ),
            'preco' => $products->sortBy(
                fn ($p) => (float) ($p->price ?? 0),
                SORT_NUMERIC,
                $desc,
            ),
            'tamanho' => $products->sortBy(
                fn ($p) => $this->sizeResolver->resolve($p),
                SORT_NUMERIC,
                $desc,
            ),
            'score_abc' => $products->sortBy(
                fn ($p) => match ($this->abcClassMap[$p->id] ?? 'B') {
                    'A' => 0,
                    'B' => 1,
                    'C' => 2,
                    default => 1,
                },
                SORT_NUMERIC,
                $desc,
            ),
            'margem' => $products->sortBy(
                fn ($p) => (float) ($this->zoneMetricsMap[$p->id]['margem'] ?? 0),
                SORT_NUMERIC,
                $desc,
            ),
            default => $products,
        };
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

        $zone = ShelfZoneResolver::resolve((int) $shelf->shelf_position, $numShelves);

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
    private function distributeInShelf(
        Collection $products,
        Section $section,
        Shelf $shelf,
        PlanogramTemplateSlot $slot,
        float $available,
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
                $rejected->push([
                    'product' => $product,
                    'reason' => PlacementFailureReason::NoHorizontalSpace,
                    'slot_id' => $slot->id,
                ]);
            }
        }

        // Phase 2: expand facings with leftover space
        if ($slot->facing_expansion !== FacingExpansion::None && $placedItems !== []) {
            [$placedItems, $occupied] = $this->expandFacings($placedItems, $slot, $available, $occupied);
        }

        // Build readonly PlacedSegment DTOs
        $placed = collect();
        $x = 0.0;

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

        // Fallback only for NoHorizontalSpace — MissingDimensions must not be retried
        $noSpaceRejected = $rejected->where('reason', PlacementFailureReason::NoHorizontalSpace)->values();

        if ($noSpaceRejected->isNotEmpty()) {
            $fallback = $this->applyFallback($noSpaceRejected, $available - $occupied, $slot, $section, $shelf, $ordering);
            $placed = $placed->merge($fallback['placed']);
            $noSpaceRejected = $fallback['remaining'];
        }

        $missingDimRejected = $rejected->where('reason', PlacementFailureReason::MissingDimensions)->values();

        return ['placed' => $placed, 'rejected' => $noSpaceRejected->merge($missingDimRejected)];
    }

    /**
     * Phase 2: distribute leftover shelf space as extra facings.
     *
     * Respeita `max_facings` como teto absoluto e os limites relativos de participação
     * (`max_share_per_sku`, `max_share_per_brand`, `max_share_per_subcategory`) como tetos adicionais.
     * O menor limite entre os dois vence. Limites null são ignorados (comportamento original).
     *
     * @param  array<int, array{product: mixed, facings: int, singleWidth: float, ordering: int}>  $placedItems
     * @return array{0: array<int, array{product: mixed, facings: int, singleWidth: float, ordering: int}>, 1: float}
     */
    private function expandFacings(array $placedItems, PlanogramTemplateSlot $slot, float $available, float $occupied): array
    {
        $maxFacings = max($slot->max_facings, 1);
        $remainingWidth = $available - $occupied;

        if ($remainingWidth <= 0 || $maxFacings <= 1) {
            return [$placedItems, $occupied];
        }

        $expansionOrder = $this->expansionOrder($placedItems, $slot->facing_expansion);

        // Round-robin: give +1 facing per pass until space runs out or all hit max_facings
        $changed = true;

        while ($changed && $remainingWidth > 0) {
            $changed = false;

            foreach ($expansionOrder as $idx) {
                if ($placedItems[$idx]['facings'] >= $maxFacings) {
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

        if ($slot->space_fallback->value === 'reduce_facings') {
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
}
