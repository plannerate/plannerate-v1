<?php

use App\Services\AutoPlanogram\DTO\PlacementResult;
use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\DTO\PlanogramInput;
use App\Services\AutoPlanogram\Validation\PlanogramValidator;
use App\Services\AutoPlanogram\Validation\Rules\UnplacedProductsRule;
use Callcocam\LaravelRaptorPlannerate\Enums\PlacementFailureReason;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;

function placementInputForValidation(): PlanogramInput
{
    return new PlanogramInput(
        planogramId: 'planogram-test',
        gondolaId: 'gondola-test',
        tenantId: 'tenant-test',
        products: collect(),
        sections: collect(),
        settings: new PlacementSettings(
            strategy: 'abc',
            useExistingAnalysis: false,
            startDate: null,
            endDate: null,
        ),
    );
}

function rejectedProduct(string $id, PlacementFailureReason $reason): array
{
    $product = new Product;
    $product->id = $id;
    $product->name = 'Produto '.$id;

    return ['product' => $product, 'reason' => $reason];
}

test('validation passes with warning when placement rejects products by horizontal space', function (): void {
    // NoHorizontalSpace não é uma regra hard (não é Blocked/MandatoryNoSpace),
    // portanto gera Warning e não impede a aprovação do planograma.
    $validator = new PlanogramValidator([new UnplacedProductsRule]);
    $result = new PlacementResult(
        placedSegments: collect(),
        rejectedProducts: collect([
            rejectedProduct('P01', PlacementFailureReason::NoHorizontalSpace),
        ]),
    );

    $report = $validator->validate(collect(), placementInputForValidation(), $result);

    expect($report->passed)->toBeTrue()
        ->and($report->errorCount)->toBe(0)
        ->and($report->warningCount)->toBe(1);
});

test('validation passes with warning when placement rejects only by physical height', function (): void {
    $validator = new PlanogramValidator([new UnplacedProductsRule]);
    $result = new PlacementResult(
        placedSegments: collect(),
        rejectedProducts: collect([
            rejectedProduct('P02', PlacementFailureReason::HeightExceedsShelf),
        ]),
    );

    $report = $validator->validate(collect(), placementInputForValidation(), $result);

    expect($report->passed)->toBeTrue()
        ->and($report->errorCount)->toBe(0)
        ->and($report->warningCount)->toBe(1);
});
