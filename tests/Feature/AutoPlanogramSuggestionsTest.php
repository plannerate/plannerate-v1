<?php

use App\Services\AutoPlanogram\DTO\PlanogramOutput;
use App\Services\AutoPlanogram\DTO\ValidationReport;
use App\Services\AutoPlanogram\Template\SlotSuggestionGenerator;

// ── helpers ───────────────────────────────────────────────────────────────────

function makeSlotRow(float $livre, int $rejeitados = 0, string $grouping = 'Bebidas'): array
{
    $total = 100.0;
    $usada = max(0.0, $total - $livre);

    return [
        'slot_id' => 'slot-'.uniqid(),
        'grouping' => $grouping,
        'module_number' => 1,
        'shelf_order' => 1,
        'shelf_id' => 'shelf-'.uniqid(),
        'largura_total' => $total,
        'largura_usada' => $usada,
        'largura_livre' => $livre,
        'percentual_uso' => (int) round(($usada / $total) * 100),
        'produtos_posicionados' => 2,
        'produtos_rejeitados' => $rejeitados,
        'produtos_rejeitados_nomes' => $rejeitados > 0 ? array_map(fn ($i) => "Produto {$i}", range(1, $rejeitados)) : [],
    ];
}

function makeTestPlanogramOutput(array $slotAnalysis = [], array $suggestions = []): PlanogramOutput
{
    $report = new ValidationReport(passed: true);

    return new PlanogramOutput(
        gondolaId: 'gondola-test',
        placedSegments: collect(),
        rejectedProducts: collect(),
        validationReport: $report,
        scoreType: 'neutral',
        slotAnalysis: $slotAnalysis,
        suggestions: $suggestions,
    );
}

// ── testes ────────────────────────────────────────────────────────────────────

it('PlanogramOutput carries slotAnalysis from template mode', function () {
    $slotAnalysis = [makeSlotRow(40.0)];
    $output = makeTestPlanogramOutput(slotAnalysis: $slotAnalysis);

    expect($output->slotAnalysis)->toHaveCount(1)
        ->and($output->slotAnalysis[0]['largura_livre'])->toBe(40.0);
});

it('PlanogramOutput carries suggestions from template mode', function () {
    $slotAnalysis = [makeSlotRow(40.0)];
    $generator = new SlotSuggestionGenerator;
    $suggestions = $generator->generate($slotAnalysis);

    $output = makeTestPlanogramOutput(slotAnalysis: $slotAnalysis, suggestions: $suggestions);

    expect($output->suggestions)->toHaveCount(1)
        ->and($output->suggestions[0]['tipo'])->toBe('espaco_disponivel');
});

it('has_space is true when any slot has more than 10cm free', function () {
    $slotAnalysis = [
        makeSlotRow(livre: 5.0),
        makeSlotRow(livre: 40.0),
    ];

    $hasSpace = collect($slotAnalysis)->some(fn ($s) => $s['largura_livre'] > 10);

    expect($hasSpace)->toBeTrue();
});

it('has_space is false when all slots have 10cm or less free', function () {
    $slotAnalysis = [
        makeSlotRow(livre: 5.0),
        makeSlotRow(livre: 8.0),
    ];

    $hasSpace = collect($slotAnalysis)->some(fn ($s) => $s['largura_livre'] > 10);

    expect($hasSpace)->toBeFalse();
});

it('has_rejects is true when any slot has rejected products', function () {
    $slotAnalysis = [makeSlotRow(livre: 0.0, rejeitados: 2)];

    $hasRejects = collect($slotAnalysis)->some(fn ($s) => $s['produtos_rejeitados'] > 0);

    expect($hasRejects)->toBeTrue();
});

it('suggestions are empty when no space issues and no rejects', function () {
    $generator = new SlotSuggestionGenerator;
    $slotAnalysis = [makeSlotRow(livre: 5.0, rejeitados: 0)]; // 5cm — abaixo do mínimo de 10cm

    expect($generator->generate($slotAnalysis))->toBe([]);
});

it('PlanogramOutput defaults slotAnalysis and suggestions to empty arrays', function () {
    $report = new ValidationReport(passed: true);
    $output = new PlanogramOutput(
        gondolaId: 'g',
        placedSegments: collect(),
        rejectedProducts: collect(),
        validationReport: $report,
    );

    expect($output->slotAnalysis)->toBe([])
        ->and($output->suggestions)->toBe([]);
});
