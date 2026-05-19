<?php

namespace App\Services\AutoPlanogram\Placement;

use App\Enums\BrandExposure;
use App\Enums\PlacementFailureReason;
use App\Enums\PriceOrder;
use App\Enums\SizeOrder;
use App\Models\Category;
use App\Models\PlanogramSubtemplate;
use App\Models\PlanogramTemplateSlot;
use App\Models\Scopes\TenantScope;
use App\Services\AutoPlanogram\DTO\OrderedBlock;
use App\Services\AutoPlanogram\DTO\PlacedLayer;
use App\Services\AutoPlanogram\DTO\PlacedSegment;
use App\Services\AutoPlanogram\DTO\PlacementResult;
use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\ProductWidthResolver;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Section;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Shelf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class TemplatePlacementEngine implements PlacementEngineInterface
{
    /** @var array<string, list<string>> Cache de descendentes por category_id dentro de uma geração */
    private array $descendantsCache = [];

    public function __construct(
        private readonly ProductWidthResolver $widthResolver,
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
        $subtemplate = $this->resolveSubtemplate($settings);

        if ($subtemplate === null) {
            Log::warning('TemplatePlacementEngine: sem subtemplate para N módulos — usando greedy', [
                'num_modules' => $sections->count(),
                'template_id' => $settings->templateId,
            ]);

            return $this->greedyPlacer->place($orderedBlocks, $sections, $settings, $reservedWidthPerShelf);
        }

        $placed = collect();
        $rejected = collect();
        $groupingsSemProduto = 0;
        $slotAnalysis = [];

        $slots = $subtemplate->slots()
            ->withoutGlobalScope(TenantScope::class)
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

            $ordered = $this->orderCandidates($candidates, $slot);
            $available = $this->getShelfAvailableWidth($section, $shelf);
            $slotResult = $this->distributeInShelf($ordered, $section, $shelf, $slot, $available);

            $placed = $placed->merge($slotResult['placed']);
            $rejected = $rejected->merge($slotResult['rejected']);

            $occupied = round((float) $slotResult['placed']->sum('width'), 1);
            $livre = round(max(0.0, $available - $occupied), 1);
            $slotAnalysis[] = [
                'slot_id' => $slot->id,
                'category_id' => $slot->category_id,
                'module_number' => $slot->module_number,
                'shelf_order' => $slot->shelf_order,
                'shelf_id' => $shelf->getKey(),
                'largura_total' => round($available, 1),
                'largura_usada' => $occupied,
                'largura_livre' => $livre,
                'percentual_uso' => $available > 0 ? (int) round(($occupied / $available) * 100) : 0,
                'produtos_posicionados' => $slotResult['placed']->count(),
                'produtos_rejeitados' => $slotResult['rejected']->count(),
                'produtos_rejeitados_nomes' => $slotResult['rejected']
                    ->filter(fn ($r) => $r['product'] !== null)
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
        ]);

        Log::info('TemplatePlacementEngine: análise de espaço por slot', [
            'slots' => collect($slotAnalysis)->map(fn ($s) => [
                'category_id' => $s['category_id'],
                'shelf_order' => $s['shelf_order'],
                'uso_percentual' => $s['percentual_uso'].'%',
                'largura_livre' => $s['largura_livre'].'cm',
                'rejeitados' => $s['produtos_rejeitados'],
            ])->toArray(),
        ]);

        return new PlacementResult($placed, $rejected, $slotAnalysis);
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

    /** @param Collection<int, mixed> $settings->products */
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
        )->values();
    }

    /** @return list<string> */
    private function getDescendantsCached(string $categoryId): array
    {
        return $this->descendantsCache[$categoryId]
            ??= Category::getDescendantIds($categoryId);
    }

    private function orderCandidates(Collection $products, PlanogramTemplateSlot $slot): Collection
    {
        $sorted = $products;

        if ($slot->size_order !== SizeOrder::None) {
            $sorted = $sorted->sortBy(
                fn ($p) => $this->parseSize((string) ($p->package_content ?? '0')),
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

        return $sorted->values();
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
        $placed = collect();
        $rejected = collect();
        $occupied = 0.0;
        $ordering = 0;

        foreach ($products as $product) {
            $facing = max($slot->min_facings, 1);
            $productWidth = $this->widthResolver->resolve($product);
            $width = (int) round($productWidth * $facing);

            if ($occupied + $width <= $available) {
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
                            quantity: $facing,
                            height: 1,
                        ),
                    ]),
                ));
                $occupied += $width;
            } else {
                $rejected->push([
                    'product' => $product,
                    'reason' => PlacementFailureReason::NoHorizontalSpace,
                    'slot_id' => $slot->id,
                ]);
            }
        }

        if ($rejected->isNotEmpty()) {
            $fallback = $this->applyFallback($rejected, $available - $occupied, $slot, $section, $shelf, $ordering);
            $placed = $placed->merge($fallback['placed']);
            $rejected = $fallback['remaining'];
        }

        return ['placed' => $placed, 'rejected' => $rejected];
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

    private function getShelfAvailableWidth(Section $section, Shelf $shelf): float
    {
        $sectionWidth = (float) ($section->width ?? 100.0);
        $cremalheiraWidth = (float) ($section->cremalheira_width ?? 0.0);

        return max(0.0, $sectionWidth - $cremalheiraWidth);
    }

    private function parseSize(string $content): float
    {
        preg_match('/[\d.]+/', $content, $matches);
        $value = (float) ($matches[0] ?? 0);
        $unit = strtolower((string) preg_replace('/[\d. ]+/', '', $content));

        return match ($unit) {
            'ml' => $value / 1000,
            'g' => $value / 1000,
            'l', 'kg' => $value,
            default => $value,
        };
    }

    private function recordSubtemplateUsed(string $planogramId, string $subtemplateId): void
    {
        DB::connection('tenant')->table('planograms')
            ->where('id', $planogramId)
            ->update(['subtemplate_id' => $subtemplateId]);
    }
}
