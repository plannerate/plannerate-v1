<?php

use App\Enums\AdjacencyRuleType;
use App\Services\AutoPlanogram\Adjacency\AdjacencyMatrix;
use App\Services\AutoPlanogram\Adjacency\RuleBasedResolver;
use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\DTO\ProductBlock;
use App\Services\AutoPlanogram\Scoring\ScoringWeightsValue;
use Illuminate\Support\Str;

function resolverSettings(): PlacementSettings
{
    return new PlacementSettings(
        strategy: 'mix',
        useExistingAnalysis: false,
        startDate: null,
        endDate: null,
        tenantId: (string) Str::ulid(),
        weights: new ScoringWeightsValue(0.4, 0.3, 0.2, 0.1, 4, 6, 4),
    );
}

function fakeBlock(string $key, float $score, ?string $adjacencyCategoryId): ProductBlock
{
    return new ProductBlock(
        children: collect(),
        aggregateScore: $score,
        groupingKey: $key,
        totalWidthEstimate: 10,
        blockHierarchyLevel: 6,
        adjacencyCategoryId: $adjacencyCategoryId,
    );
}

function resolverWithMatrix(AdjacencyMatrix $matrix): RuleBasedResolver
{
    return new class($matrix) extends RuleBasedResolver
    {
        public function __construct(private readonly AdjacencyMatrix $matrix) {}

        protected function loadMatrix(?string $tenantId): AdjacencyMatrix
        {
            return $this->matrix;
        }
    };
}

test('MUST_AVOID nunca fica adjacente quando ha alternativa', function (): void {
    $a = fakeBlock('A', 100, 'cat-a');
    $b = fakeBlock('B', 90, 'cat-b');
    $c = fakeBlock('C', 80, 'cat-c');

    $matrix = new AdjacencyMatrix([
        'cat-a' => [
            'cat-b' => ['type' => AdjacencyRuleType::MustAvoid, 'weight' => -100.0],
        ],
        'cat-b' => [
            'cat-a' => ['type' => AdjacencyRuleType::MustAvoid, 'weight' => -100.0],
        ],
    ]);

    $ordered = resolverWithMatrix($matrix)->resolve(collect([$a, $b, $c]), resolverSettings());

    expect($ordered->pluck('block.groupingKey')->all())->toBe(['A', 'C', 'B']);
});

test('MUST_BE_NEAR fica adjacente quando possivel', function (): void {
    $a = fakeBlock('A', 100, 'cat-a');
    $b = fakeBlock('B', 70, 'cat-b');
    $c = fakeBlock('C', 80, 'cat-c');

    $matrix = new AdjacencyMatrix([
        'cat-a' => [
            'cat-b' => ['type' => AdjacencyRuleType::MustBeNear, 'weight' => 50.0],
        ],
        'cat-b' => [
            'cat-a' => ['type' => AdjacencyRuleType::MustBeNear, 'weight' => 50.0],
        ],
    ]);

    $ordered = resolverWithMatrix($matrix)->resolve(collect([$a, $b, $c]), resolverSettings());

    expect($ordered->pluck('block.groupingKey')->all())->toBe(['A', 'B', 'C']);
});

test('sem regras a ordem segue o score agregado', function (): void {
    $ordered = resolverWithMatrix(new AdjacencyMatrix([]))->resolve(
        collect([
            fakeBlock('A', 10, 'cat-a'),
            fakeBlock('B', 30, 'cat-b'),
            fakeBlock('C', 20, 'cat-c'),
        ]),
        resolverSettings(),
    );

    expect($ordered->pluck('block.groupingKey')->all())->toBe(['B', 'C', 'A']);
});
