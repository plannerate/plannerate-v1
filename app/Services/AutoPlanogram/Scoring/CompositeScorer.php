<?php

namespace App\Services\AutoPlanogram\Scoring;

use App\Models\ScoringWeights;
use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\DTO\ScoredProduct;
use Callcocam\LaravelRaptorPlannerate\Concerns\UsesPlannerateTenantDatabase;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Illuminate\Support\Collection;

class CompositeScorer implements ProductScorerInterface
{
    use UsesPlannerateTenantDatabase;

    public function __construct(
        private readonly SalesMetricsRepository $sales,
    ) {}

    /**
     * @param  Collection<int, Product>  $products
     * @return Collection<int, ScoredProduct>
     */
    public function score(Collection $products, PlacementSettings $settings): Collection
    {
        if ($products->isEmpty()) {
            return collect();
        }

        $tenantId = $settings->tenantId;
        $weights = $settings->weights ?? $this->resolveWeights($tenantId);

        $metrics = $this->sales->fetchMetrics(
            tenantId: $tenantId ?? '',
            productIds: $products->pluck('id'),
            windowMonths: $weights->salesWindowMonths,
            storeId: $settings->storeId,
        );

        $strategicIds = $this->loadStrategicIds($tenantId ?? '');

        // Normalização min-max por categoria pai no nível 4 (Categoria)
        $byCategory = $products->groupBy(
            fn ($p) => $this->resolveCategoryAtLevel($p, 4)
        );

        return $byCategory
            ->flatMap(fn ($group) => $this->scoreGroup($group, $metrics, $strategicIds, $weights))
            ->sortByDesc('score')
            ->values();
    }

    /** @param  Collection<int, Product>  $group */
    private function scoreGroup(
        Collection $group,
        array $metrics,
        array $strategicIds,
        ScoringWeightsValue $weights,
    ): Collection {
        $quantities = $group->map(fn ($p) => $metrics[$p->id]['quantity'] ?? 0);
        $margens = $group->map(fn ($p) => $metrics[$p->id]['margem'] ?? 0.0);

        $qMin = $quantities->min();
        $qMax = $quantities->max();
        $mMin = $margens->min();
        $mMax = $margens->max();

        return $group->map(function ($p) use ($metrics, $strategicIds, $weights, $qMin, $qMax, $mMin, $mMax): ScoredProduct {
            $m = $metrics[$p->id] ?? ['quantity' => 0, 'margem' => 0.0, 'doh' => null];

            $giroNorm = $this->minMax((float) $m['quantity'], (float) $qMin, (float) $qMax);
            $margemNorm = $this->minMax((float) $m['margem'], (float) $mMin, (float) $mMax);
            $dohNorm = $m['doh'] === null ? 0.5 : $this->normalizeDoh((float) $m['doh']);
            $strategic = in_array($p->id, $strategicIds, true) ? 1.0 : 0.0;

            $score = ($giroNorm * $weights->giro)
                   + ($margemNorm * $weights->margem)
                   + ($strategic * $weights->estrategico)
                   + ((1 - $dohNorm) * $weights->doh);

            return new ScoredProduct(
                productId: $p->id,
                ean: (string) ($p->ean ?? $p->codigo_erp ?? ''),
                score: $score,
                product: $p,
                metadata: [
                    'giro_norm' => $giroNorm,
                    'margem_norm' => $margemNorm,
                    'doh_norm' => $dohNorm,
                    'strategic' => $strategic,
                    'raw_quantity' => $m['quantity'],
                    'raw_margem' => $m['margem'],
                ],
            );
        });
    }

    private function minMax(float $v, float $min, float $max): float
    {
        if ($max <= $min) {
            return 0.5;
        }

        return ($v - $min) / ($max - $min);
    }

    private function normalizeDoh(float $doh): float
    {
        return min(1.0, $doh / 60.0);
    }

    private function resolveCategoryAtLevel(Product $product, int $targetLevel): ?string
    {
        if (! $product->category) {
            return null;
        }

        $cat = $product->category;

        while ($cat && $cat->hierarchy_position > $targetLevel) {
            $cat = $cat->relationLoaded('parent') ? $cat->parent : $cat->parent()->first();
        }

        return $cat?->hierarchy_position === $targetLevel ? $cat->id : null;
    }

    protected function loadStrategicIds(string $tenantId): array
    {
        return $this->plannerateTenantTable('product_strategic_flags')
            ->where('tenant_id', $tenantId)
            ->where('is_strategic', true)
            ->pluck('product_id')
            ->all();
    }

    private function resolveWeights(?string $tenantId): ScoringWeightsValue
    {
        if ($tenantId === null) {
            return ScoringWeightsValue::default();
        }

        $model = ScoringWeights::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->first();

        return $model ? ScoringWeightsValue::fromModel($model) : ScoringWeightsValue::default();
    }
}
