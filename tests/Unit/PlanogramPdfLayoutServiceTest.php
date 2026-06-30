<?php

use Callcocam\LaravelRaptorPlannerate\Services\Export\PlanogramPdfLayoutService;

/**
 * Monta um payload mínimo no formato de GondolaPrintService::prepareGondolaData().
 *
 * @param  array<int, array<string, mixed>>  $sections
 * @return array<string, mixed>
 */
function pdfFakeGondolaData(array $sections, string $flow = 'left_to_right', string $alignment = 'justify'): array
{
    return [
        'gondola' => [
            'id' => 'g1',
            'slug' => 'gondola-teste',
            'name' => 'Gôndola Teste',
            'flow' => $flow,
            'alignment' => $alignment,
            'planogram' => null,
        ],
        'sections' => $sections,
    ];
}

/**
 * Seção sintética com uma prateleira contendo um segmento.
 *
 * @return array<string, mixed>
 */
function pdfFakeSection(string $id, int $ordering, string $productType = 'normal', int $facings = 2, int $rows = 1): array
{
    return [
        'id' => $id,
        'ordering' => $ordering,
        'width' => 100,
        'height' => 200,
        'hole_width' => 2,
        'hole_height' => 3,
        'hole_spacing' => 2,
        'base_height' => 20,
        'cremalheira_width' => 4,
        'shelves' => [
            [
                'id' => $id.'-s1',
                'shelf_position' => 100,
                'shelf_height' => 4,
                'shelf_width' => 100,
                'shelf_depth' => 40,
                'product_type' => $productType,
                'segments' => [
                    [
                        'id' => $id.'-seg1',
                        'position' => 0,
                        'quantity' => $rows,
                        'layer' => [
                            'quantity' => $facings,
                            'product' => [
                                'id' => 'p1',
                                'name' => 'Produto X',
                                'width' => 10,
                                'height' => 15,
                                'depth' => 5,
                                'image_url_encoded' => null,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
}

it('builds row layout with one module per section, fitted to the page', function (): void {
    $service = new PlanogramPdfLayoutService;

    $data = pdfFakeGondolaData([
        pdfFakeSection('a', 1),
        pdfFakeSection('b', 2),
    ]);

    $layout = $service->buildRowLayout($data);

    expect($layout['modules'])->toHaveCount(2)
        // Fit-to-page: nunca estoura a caixa de conteúdo (1080 x 470 px).
        ->and($layout['bandWidth'])->toBeLessThanOrEqual(1080.5)
        ->and($layout['bandHeight'])->toBeLessThanOrEqual(470.5)
        // Ordem do fluxo LTR mantém ordering crescente.
        ->and($layout['modules'][0]['ordering'])->toBe(1)
        ->and($layout['modules'][1]['ordering'])->toBe(2)
        // Segundo módulo posicionado à direita do primeiro.
        ->and($layout['modules'][1]['left'])->toBeGreaterThan($layout['modules'][0]['left']);
});

it('reverses module order when flow is right_to_left', function (): void {
    $service = new PlanogramPdfLayoutService;

    $data = pdfFakeGondolaData([
        pdfFakeSection('a', 1),
        pdfFakeSection('b', 2),
    ], flow: 'right_to_left');

    $layout = $service->buildRowLayout($data);

    expect($layout['modules'][0]['ordering'])->toBe(2)
        ->and($layout['modules'][1]['ordering'])->toBe(1);
});

it('expands facings and stacking into individual cells anchored to the base', function (): void {
    $service = new PlanogramPdfLayoutService;

    // 3 frentes x 2 empilhamentos = 6 células.
    $data = pdfFakeGondolaData([pdfFakeSection('a', 1, facings: 3, rows: 2)]);

    $layout = $service->buildRowLayout($data);
    $cells = $layout['modules'][0]['shelves'][0]['cells'];

    expect($cells)->toHaveCount(6)
        ->and($cells[0]['anchor'])->toBe('bottom')
        ->and($cells[0])->toHaveKey('bottom')
        ->and($cells[0])->not->toHaveKey('top');
});

it('anchors hook (gancheira) shelves to the top so products hang down', function (): void {
    $service = new PlanogramPdfLayoutService;

    $data = pdfFakeGondolaData([pdfFakeSection('a', 1, productType: 'hook')]);

    $layout = $service->buildRowLayout($data);
    $cells = $layout['modules'][0]['shelves'][0]['cells'];

    expect($cells[0]['anchor'])->toBe('top')
        ->and($cells[0])->toHaveKey('top')
        ->and($cells[0])->not->toHaveKey('bottom');
});

it('filters modules by sectionIds in column layout', function (): void {
    $service = new PlanogramPdfLayoutService;

    $data = pdfFakeGondolaData([
        pdfFakeSection('a', 1),
        pdfFakeSection('b', 2),
    ]);

    $pages = $service->buildModulesLayout($data, ['b']);

    expect($pages)->toHaveCount(1)
        ->and($pages[0]['ordering'])->toBe(2)
        ->and($pages[0]['rawWidthCm'])->toBe(100.0);
});

it('computes hole positions consistent with the section geometry', function (): void {
    $service = new PlanogramPdfLayoutService;

    // height 200, base 20 -> usable 180; (holeH 3 + spacing 2)=5 -> 36 furos.
    $data = pdfFakeGondolaData([pdfFakeSection('a', 1)]);

    $layout = $service->buildRowLayout($data);

    expect($layout['modules'][0]['holes'])->toHaveCount(36);
});
