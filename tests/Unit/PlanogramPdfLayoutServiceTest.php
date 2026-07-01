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

it('shrinks facings horizontally to fit the section width on overflow', function (): void {
    $service = new PlanogramPdfLayoutService;

    // Seção de 100cm, produto de 10cm: 15 frentes = 150cm somados (> 100 = overflow).
    $data = pdfFakeGondolaData([pdfFakeSection('a', 1, facings: 15)]);

    $layout = $service->buildRowLayout($data);
    $shelf = $layout['modules'][0]['shelves'][0];
    $cells = $shelf['cells'];
    $areaWidth = $shelf['areaWidth'];

    // 15 células, todas encolhidas para caber: a borda direita da última
    // célula coincide com a largura da área (não transborda).
    $last = $cells[count($cells) - 1];
    $rightEdge = $last['left'] + $last['width'];

    expect($cells)->toHaveCount(15)
        ->and($rightEdge)->toBeLessThanOrEqual($areaWidth + 0.5)
        ->and($rightEdge)->toBeGreaterThan($areaWidth - 0.5)
        // Largura encolhida ≈ areaWidth/15 (fit = 100/150 aplicado só na largura).
        ->and($cells[0]['width'])->toEqualWithDelta($areaWidth / 15, 0.5)
        // Altura preservada: 15cm físicos, independente do encaixe horizontal.
        ->and($cells[0]['height'])->toEqualWithDelta($areaWidth / 100 * 15, 0.5);
});

it('does not shrink facings when they fit within the section width', function (): void {
    $service = new PlanogramPdfLayoutService;

    // 2 frentes = 20cm somados em 100cm: sem overflow, largura física preservada.
    $data = pdfFakeGondolaData([pdfFakeSection('a', 1, facings: 2)]);

    $layout = $service->buildRowLayout($data);
    $shelf = $layout['modules'][0]['shelves'][0];
    $cells = $shelf['cells'];
    $areaWidth = $shelf['areaWidth'];

    // Largura física de 10cm preservada (≈ areaWidth/10), sem encaixe.
    expect($cells[0]['width'])->toEqualWithDelta($areaWidth / 100 * 10, 0.5);
});

it('does not let tall top-shelf products overflow above the module top', function (): void {
    $service = new PlanogramPdfLayoutService;

    // Prateleira de cima (shelf_position 10, medido a partir do topo) com um
    // produto ALTO (100cm): sem a folga dinâmica, o topo da pilha ficaria bem
    // acima do topo do módulo (estouro de ~90cm).
    $section = pdfFakeSection('a', 1);
    $section['shelves'][0]['shelf_position'] = 10;
    $section['shelves'][0]['segments'][0]['layer']['product']['height'] = 100;

    $data = pdfFakeGondolaData([$section]);

    foreach (['row' => $service->buildRowLayout($data)['modules'][0],
        'col' => $service->buildModulesLayout($data)[0]] as $module) {
        $shelf = $module['shelves'][0];

        // Topo de cada célula a partir do topo do módulo (célula ancorada pela
        // base): areaTop + areaHeight - bottom - height. Não pode ser < 0.
        $minTop = min(array_map(
            fn ($cell) => $shelf['areaTop'] + $shelf['areaHeight'] - $cell['bottom'] - $cell['height'],
            $shelf['cells'],
        ));

        expect($minTop)->toBeGreaterThanOrEqual(-0.5);
    }
});

it('builds a per-shelf products list with erp code, ean and aggregated facings', function (): void {
    $service = new PlanogramPdfLayoutService;

    // Seção com dois segmentos: o mesmo produto (3 frentes) e outro (2 frentes).
    $section = pdfFakeSection('a', 1);
    $section['shelves'][0]['segments'] = [
        [
            'id' => 'seg1',
            'quantity' => 1,
            'layer' => [
                'quantity' => 3,
                'product' => ['id' => 'p1', 'name' => 'Produto X', 'codigo_erp' => '25750', 'ean' => '7897520100401', 'brand' => 'DIVERSAS', 'width' => 10, 'height' => 15],
            ],
        ],
        [
            'id' => 'seg2',
            'quantity' => 1,
            'layer' => [
                'quantity' => 2,
                'product' => ['id' => 'p1', 'name' => 'Produto X', 'codigo_erp' => '25750', 'ean' => '7897520100401', 'brand' => 'DIVERSAS', 'width' => 10, 'height' => 15],
            ],
        ],
        [
            'id' => 'seg3',
            'quantity' => 1,
            'layer' => [
                'quantity' => 4,
                'product' => ['id' => 'p2', 'name' => 'Produto Y', 'codigo_erp' => '88959', 'ean' => '7898645221811', 'brand' => 'DIVERSAS', 'width' => 10, 'height' => 15],
            ],
        ],
    ];

    $data = pdfFakeGondolaData([$section]);
    $pages = $service->buildModulesLayout($data);
    $products = $pages[0]['shelves'][0]['products'];

    // Dois produtos distintos; o primeiro soma as frentes dos dois segmentos.
    expect($products)->toHaveCount(2)
        ->and($products[0]['codigo_erp'])->toBe('25750')
        ->and($products[0]['ean'])->toBe('7897520100401')
        ->and($products[0]['frentes'])->toBe(5)
        ->and($products[1]['codigo_erp'])->toBe('88959')
        ->and($products[1]['frentes'])->toBe(4);
});

it('computes hole positions consistent with the section geometry', function (): void {
    $service = new PlanogramPdfLayoutService;

    // height 200, base 20 -> usable 180; (holeH 3 + spacing 2)=5 -> 36 furos.
    $data = pdfFakeGondolaData([pdfFakeSection('a', 1)]);

    $layout = $service->buildRowLayout($data);

    expect($layout['modules'][0]['holes'])->toHaveCount(36);
});
