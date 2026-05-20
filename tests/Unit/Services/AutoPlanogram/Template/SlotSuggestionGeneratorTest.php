<?php

use App\Services\AutoPlanogram\Template\SlotSuggestionGenerator;

// ── helpers ───────────────────────────────────────────────────────────────────

function makeSlotAnalysis(
    float $larguraTotal = 100.0,
    float $larguraUsada = 60.0,
    int $rejeitados = 0,
    array $rejeitadosNomes = [],
    int $shelfOrder = 1,
    int $moduleNumber = 1,
    string $categoryName = 'Bebidas',
    string $categoryId = 'cat-bebidas',
): array {
    $livre = max(0.0, $larguraTotal - $larguraUsada);

    return [
        'slot_id' => 'slot-'.uniqid(),
        'category_id' => $categoryId,
        'category_name' => $categoryName,
        'module_number' => $moduleNumber,
        'shelf_order' => $shelfOrder,
        'shelf_id' => 'shelf-'.uniqid(),
        'largura_total' => $larguraTotal,
        'largura_usada' => $larguraUsada,
        'largura_livre' => $livre,
        'percentual_uso' => $larguraTotal > 0 ? (int) round(($larguraUsada / $larguraTotal) * 100) : 0,
        'produtos_posicionados' => 3,
        'produtos_rejeitados' => $rejeitados,
        'produtos_rejeitados_nomes' => $rejeitadosNomes,
    ];
}

// ── testes ────────────────────────────────────────────────────────────────────

it('returns empty array when slot analysis is empty', function () {
    $generator = new SlotSuggestionGenerator;

    expect($generator->generate([]))->toBe([]);
});

it('generates espaco_disponivel suggestion with alta priority for slot with > 30cm free', function () {
    $generator = new SlotSuggestionGenerator;
    $slot = makeSlotAnalysis(larguraTotal: 100.0, larguraUsada: 60.0); // 40cm livres

    $suggestions = $generator->generate([$slot]);

    expect($suggestions)->toHaveCount(1)
        ->and($suggestions[0]['tipo'])->toBe('espaco_disponivel')
        ->and($suggestions[0]['prioridade'])->toBe('alta')
        ->and($suggestions[0]['dados']['largura_livre'])->toBe(40.0);
});

it('generates espaco_disponivel suggestion with media priority for slot with 11-30cm free', function () {
    $generator = new SlotSuggestionGenerator;
    $slot = makeSlotAnalysis(larguraTotal: 100.0, larguraUsada: 80.0); // 20cm livres

    $suggestions = $generator->generate([$slot]);

    expect($suggestions)->toHaveCount(1)
        ->and($suggestions[0]['tipo'])->toBe('espaco_disponivel')
        ->and($suggestions[0]['prioridade'])->toBe('media');
});

it('does not generate suggestion when free space is <= 10cm', function () {
    $generator = new SlotSuggestionGenerator;
    $slot = makeSlotAnalysis(larguraTotal: 100.0, larguraUsada: 92.0); // 8cm livres

    $suggestions = $generator->generate([$slot]);

    expect($suggestions)->toBe([]);
});

it('generates capacidade_excedida suggestion when slot has rejected products', function () {
    $generator = new SlotSuggestionGenerator;
    $slot = makeSlotAnalysis(
        larguraTotal: 100.0,
        larguraUsada: 100.0,
        rejeitados: 2,
        rejeitadosNomes: ['Produto A', 'Produto B'],
    );

    $suggestions = $generator->generate([$slot]);

    expect($suggestions)->toHaveCount(1)
        ->and($suggestions[0]['tipo'])->toBe('capacidade_excedida')
        ->and($suggestions[0]['prioridade'])->toBe('alta')
        ->and($suggestions[0]['dados']['total_rejeitados'])->toBe(2)
        ->and($suggestions[0]['dados']['produtos_fora'])->toBe(['Produto A', 'Produto B']);
});

it('consolidates multiple slots with rejects into one capacidade_excedida suggestion', function () {
    $generator = new SlotSuggestionGenerator;

    $slots = [
        makeSlotAnalysis(larguraUsada: 100.0, rejeitados: 1, rejeitadosNomes: ['Produto A'], categoryName: 'Bebidas'),
        makeSlotAnalysis(larguraUsada: 100.0, rejeitados: 2, rejeitadosNomes: ['Produto B', 'Produto C'], categoryName: 'Snacks'),
    ];

    $suggestions = $generator->generate($slots);

    $excedida = collect($suggestions)->where('tipo', 'capacidade_excedida')->first();

    expect($excedida)->not->toBeNull()
        ->and($excedida['dados']['total_rejeitados'])->toBe(3)
        ->and($excedida['dados']['groupings_cheios'])->toContain('Bebidas')
        ->and($excedida['dados']['groupings_cheios'])->toContain('Snacks')
        ->and($excedida['dados']['produtos_fora'])->toHaveCount(3);
});

it('returns empty when no space available and no rejects', function () {
    $generator = new SlotSuggestionGenerator;
    $slot = makeSlotAnalysis(larguraTotal: 100.0, larguraUsada: 95.0, rejeitados: 0); // 5cm livres, sem rejeitos

    expect($generator->generate([$slot]))->toBe([]);
});

it('places alta priority suggestions before media priority', function () {
    $generator = new SlotSuggestionGenerator;

    $slots = [
        makeSlotAnalysis(larguraTotal: 100.0, larguraUsada: 80.0), // 20cm livres → media
        makeSlotAnalysis(larguraTotal: 100.0, larguraUsada: 50.0), // 50cm livres → alta
    ];

    $suggestions = $generator->generate($slots);

    $prioridades = array_column($suggestions, 'prioridade');

    expect($prioridades[0])->toBe('alta');
});
