<?php

namespace App\Services\AutoPlanogram\Scoring;

use App\Models\ScoringWeights;
use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\DTO\ScoredProduct;
use Callcocam\LaravelRaptorPlannerate\Concerns\UsesPlannerateTenantDatabase;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

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

        // Normalização global: compara todos os produtos entre si.
        // Giro usa log-transform porque vendas seguem distribuição power-law
        // (sem log, um outlier como 27 000 unidades esmaga todos os outros).
        $allQuantities = $products->map(fn ($p) => (float) ($metrics[$p->id]['quantity'] ?? 0));
        $allMargens = $products->map(fn ($p) => (float) ($metrics[$p->id]['margem'] ?? 0.0));

        $qMax = (float) $allQuantities->max();
        $mMin = (float) $allMargens->min();
        $mMax = (float) $allMargens->max();

        Log::debug('CompositeScorer: bounds globais', [
            'produtos' => $products->count(),
            'q_max' => round($qMax, 2),
            'm_min' => round($mMin, 2),
            'm_max' => round($mMax, 2),
        ]);

        $scored = $products
            ->map(fn ($p) => $this->scoreProduct($p, $metrics, $strategicIds, $weights, $qMax, $mMin, $mMax))
            ->sortByDesc('score')
            ->values();

        $this->logScoreDistribution($scored);

        return $scored;
    }

    public function scoreOrNeutral(Collection $products, PlacementSettings $settings): Collection
    {
        if ($products->isEmpty()) {
            return collect();
        }

        $scored = $this->score($products, $settings);

        $hasRealScores = $scored->some(fn ($sp) => $sp->score > 0);

        if ($hasRealScores) {
            return $scored;
        }

        Log::info('CompositeScorer: sem dados de venda no período, aplicando score neutro', [
            'total_produtos' => $products->count(),
            'score_neutro' => 0.5,
            'motivo' => 'Modo template — layout definido pelo template, score apenas refina ordenação interna',
            'periodo_inicio' => $settings->startDate ?? 'não informado',
            'periodo_fim' => $settings->endDate ?? 'não informado',
        ]);

        return $products->map(fn ($product) => new ScoredProduct(
            productId: $product->id,
            ean: (string) ($product->ean ?? $product->codigo_erp ?? ''),
            score: 0.5,
            product: $product,
            metadata: [
                'score_type' => 'neutral',
                'giro_norm' => 0.5,
                'margem_norm' => 0.5,
                'doh_norm' => 0.5,
                'strategic' => 0.0,
                'raw_quantity' => 0,
                'raw_margem' => 0,
            ],
        ))->values();
    }

    private function scoreProduct(
        Product $p,
        array $metrics,
        array $strategicIds,
        ScoringWeightsValue $weights,
        float $qMax,
        float $mMin,
        float $mMax,
    ): ScoredProduct {
        $m = $metrics[$p->id] ?? ['quantity' => 0, 'margem' => 0.0, 'doh' => null];

        $giroNorm = $qMax > 0 ? log((float) $m['quantity'] + 1) / log($qMax + 1) : 0.0;
        $margemNorm = $this->minMax((float) $m['margem'], $mMin, $mMax);
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
                'score_type' => 'composite',
                'giro_norm' => $giroNorm,
                'margem_norm' => $margemNorm,
                'doh_norm' => $dohNorm,
                'strategic' => $strategic,
                'raw_quantity' => $m['quantity'],
                'raw_margem' => $m['margem'],
            ],
        );
    }

    private function minMax(float $v, float $min, float $max): float
    {
        if ($max <= $min) {
            return 0.0;
        }

        return ($v - $min) / ($max - $min);
    }

    private function normalizeDoh(float $doh): float
    {
        return min(1.0, $doh / 60.0);
    }

    /** @param  Collection<int, ScoredProduct>  $scored */
    private function logScoreDistribution(Collection $scored): void
    {
        $scores = $scored->pluck('score');

        Log::debug('CompositeScorer: distribuição de scores', [
            'eye_gte_070' => $scores->filter(fn ($s) => $s >= 0.70)->count(),
            'hand_035_070' => $scores->filter(fn ($s) => $s >= 0.35 && $s < 0.70)->count(),
            'low_lt_035' => $scores->filter(fn ($s) => $s < 0.35)->count(),
            'score_max' => round((float) $scores->max(), 3),
            'score_min' => round((float) $scores->min(), 3),
            'score_avg' => round((float) $scores->avg(), 3),
        ]);
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
