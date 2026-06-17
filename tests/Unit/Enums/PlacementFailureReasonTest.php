<?php

use Callcocam\LaravelRaptorPlannerate\Enums\PlacementFailureReason;

it('expõe o motivo RemovedFromMix com valor e label corretos', function (): void {
    expect(PlacementFailureReason::RemovedFromMix->value)->toBe('removed_from_mix')
        ->and(PlacementFailureReason::RemovedFromMix->label())->toBe('Retirado do mix pela análise ABC');
});

it('classifica RemovedFromMix como recomendação de mix, não falha física/dados/regra', function (): void {
    $reason = PlacementFailureReason::RemovedFromMix;

    expect($reason->isMixRecommendation())->toBeTrue()
        ->and($reason->isPhysical())->toBeFalse()
        ->and($reason->isDataQuality())->toBeFalse()
        ->and($reason->isHardRule())->toBeFalse();
});

it('motivos que não são recomendação de mix retornam isMixRecommendation false', function (): void {
    expect(PlacementFailureReason::NoHorizontalSpace->isMixRecommendation())->toBeFalse()
        ->and(PlacementFailureReason::Blocked->isMixRecommendation())->toBeFalse()
        ->and(PlacementFailureReason::ManuallyRemoved->isMixRecommendation())->toBeFalse();
});
