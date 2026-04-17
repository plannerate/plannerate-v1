<?php

use App\DTOs\Plannerate\IAGenerate\IAGenerateConfigDTO;
use App\DTOs\Plannerate\IAGenerate\PlanogramContextDTO;
use App\Services\Plannerate\IAGenerate\IAPromptBuilderService;

uses(Tests\TestCase::class);

it('builds a detailed prompt with full context and output contract', function () {
    $service = app(IAPromptBuilderService::class);

    $context = new PlanogramContextDTO(
        gondolaId: 'gondola-1',
        gondolaData: [
            'id' => 'gondola-1',
            'name' => 'Gondola Teste',
            'width' => 360,
            'height' => 200,
            'depth' => 40,
        ],
        shelves: [
            [
                'id' => 'shelf-1',
                'section_id' => 'section-1',
                'position' => 100,
                'width' => 90,
                'height' => 30,
                'depth' => 40,
                'available_space' => 3600,
            ],
        ],
        products: [
            [
                'id' => 'prod-1',
                'name' => 'Produto A',
                'category' => 'Bebidas',
                'dimensions' => ['width' => 10, 'height' => 20, 'depth' => 8],
                'abc_class' => 'A',
                'score' => 10,
            ],
        ],
        categoryHierarchy: [],
        merchandisingRules: [],
    );

    $config = new IAGenerateConfigDTO(
        categoryId: null,
        strategy: 'mix',
    );

    $prompt = $service->buildPrompt($context, $config);

    expect($prompt)->toContain('# 🎯 VOCÊ É UM ESPECIALISTA EM MERCHANDISING E PLANOGRAMAS');
    expect($prompt)->toContain('# 📊 CONTEXTO DO PLANOGRAMA');
    expect($prompt)->toContain('### Prateleiras:');
    expect($prompt)->toContain('### Produtos:');
    expect($prompt)->toContain('# 📤 FORMATO DE SAÍDA ESPERADO');
    expect($prompt)->toContain('Retorne APENAS o JSON, sem texto adicional antes ou depois');
});
