<?php

namespace Callcocam\LaravelRaptorPlannerate\Services\Export;

/**
 * Serviço de layout para o PDF da gôndola gerado no servidor (dompdf).
 *
 * Recebe o payload de {@see GondolaPrintService::prepareGondolaData()} e
 * devolve uma estrutura "burra" pronta para o Blade: cada módulo, prateleira,
 * barra e célula de produto já com posição/tamanho absolutos em PIXELS. Toda a
 * geometria que no frontend é feita com flexbox/scale (que o dompdf não
 * suporta) é resolvida aqui em PHP e convertida para posicionamento absoluto.
 *
 * As fórmulas de geometria são portadas verbatim dos composables/componentes
 * Vue para garantir o MESMO visual da tela de preview:
 * - calculateHolePositions ← useSectionHoles.ts
 * - calculateShelfArea     ← useShelfAreaCalculation.ts
 * - shelfBasePosition/justifyGap/alinhamento ← PdfShelf.vue
 * - ordenação por fluxo/posição ← PdfPreview.vue / PdfSection.vue
 */
class PlanogramPdfLayoutService
{
    /**
     * Defaults idênticos ao DEFAULT_SECTION_FIELDS (useSectionFields.ts).
     */
    private const DEFAULT_HEIGHT = 200;

    private const DEFAULT_BASE_HEIGHT = 20;

    private const DEFAULT_HOLE_HEIGHT = 3;

    private const DEFAULT_HOLE_WIDTH = 2;

    private const DEFAULT_HOLE_SPACING = 2;

    private const DEFAULT_CREMALHEIRA_WIDTH = 4;

    /** Espaçamento mínimo entre prateleiras (useShelfAreaCalculation.ts). */
    private const MIN_SPACING = 2;

    /** Altura mínima da área da prateleira (useShelfAreaCalculation.ts). */
    private const MIN_AREA_HEIGHT = 50;

    /**
     * Folga (cm) no topo do módulo p/ produtos altos da prateleira de cima.
     * Valor ÚNICO para os dois modos (em linha e por módulo), para que a
     * cremalheira e as prateleiras tenham a MESMA proporção/escala nos dois
     * PDFs. É apenas o mínimo: {@see calculateRequiredHeadroom()} aumenta a
     * folga quando o produto mais alto exigir, evitando estouro para cima.
     */
    private const TOP_HEADROOM_CM = 15;

    // --- Caixas de conteúdo (px @96dpi) usadas para o fit-to-page. Ajustáveis. ---

    /** Largura útil da fila de módulos no modo "em linha" (A4 landscape). */
    private const ROW_CONTENT_WIDTH = 1080;

    /** Altura da faixa de módulos no modo "em linha" (após header/fluxo/rodapé). */
    private const ROW_BAND_HEIGHT = 470;

    /**
     * Largura útil do módulo no modo "por módulo" (A4 portrait). O módulo é
     * empurrado para a esquerda e encolhido para abrir espaço à direita para a
     * tabela de produtos da prateleira (código ERP, EAN, frentes).
     */
    private const COL_CONTENT_WIDTH = 340;

    /**
     * Altura útil da área do módulo no modo "por módulo". Grande para o módulo
     * preencher a maior parte da página; o rodapé fica fixo no pé (ver Blade).
     */
    private const COL_CONTENT_HEIGHT = 740;

    /**
     * Monta o layout do modo "em linha" (todos os módulos lado a lado).
     *
     * @param  array<string, mixed>  $data  Saída de prepareGondolaData()
     * @return array<string, mixed>
     */
    public function buildRowLayout(array $data): array
    {
        $sections = $this->orderSections($data['sections'] ?? [], $data['gondola']['flow'] ?? 'left_to_right');
        $alignment = $data['gondola']['alignment'] ?? 'justify';

        // Dimensões em CM antes da escala, para calcular o fit-to-page.
        $totalWidthCm = 0.0;
        $maxHeightCm = 0.0;

        // Folga de topo uniforme p/ toda a fila: no mínimo TOP_HEADROOM_CM, mas
        // aumenta o suficiente para que o produto mais alto da prateleira de
        // cima de QUALQUER módulo não estoure acima do topo. Como os módulos
        // são alinhados pela base, uma folga única mantém o topo alinhado.
        $headroomCm = self::TOP_HEADROOM_CM;
        foreach ($sections as $section) {
            $headroomCm = max($headroomCm, $this->calculateRequiredHeadroom($section, self::TOP_HEADROOM_CM));
        }

        foreach ($sections as $section) {
            $cremCm = $this->cremalheiraWidth($section);
            $sectionWidthCm = (float) ($section['width'] ?? 0);
            // Largura efetiva por módulo (módulos compartilham a cremalheira).
            $totalWidthCm += $sectionWidthCm + $cremCm;
            $heightCm = (float) ($section['height'] ?? self::DEFAULT_HEIGHT) + $headroomCm;
            $maxHeightCm = max($maxHeightCm, $heightCm);
        }
        // Acrescenta a cremalheira esquerda do primeiro módulo (não compartilhada).
        if (! empty($sections)) {
            $totalWidthCm += $this->cremalheiraWidth($sections[0]);
        }

        $pxPerCm = $this->fitScale($totalWidthCm, $maxHeightCm, self::ROW_CONTENT_WIDTH, self::ROW_BAND_HEIGHT);

        // Posiciona os módulos da esquerda para a direita com sobreposição da
        // cremalheira compartilhada (igual ao marginLeft negativo do PdfSection).
        $modules = [];
        $xCursorCm = 0.0;

        foreach ($sections as $index => $section) {
            $cremCm = $this->cremalheiraWidth($section);
            $sectionWidthCm = (float) ($section['width'] ?? 0);

            $module = $this->buildModule($section, $index, $pxPerCm, $alignment, $headroomCm);
            $module['left'] = round($xCursorCm * $pxPerCm, 2);
            $modules[] = $module;

            $xCursorCm += $sectionWidthCm + $cremCm;
        }

        return [
            'modules' => $modules,
            'bandWidth' => round($totalWidthCm * $pxPerCm, 2),
            'bandHeight' => round($maxHeightCm * $pxPerCm, 2),
            'pxPerCm' => $pxPerCm,
        ];
    }

    /**
     * Monta o layout do modo "por módulo" (1 página por módulo).
     *
     * @param  array<string, mixed>  $data  Saída de prepareGondolaData()
     * @param  array<int, string>|null  $sectionIds  Filtro opcional de módulos
     * @return array<int, array<string, mixed>> Um item por página/módulo
     */
    public function buildModulesLayout(array $data, ?array $sectionIds = null): array
    {
        $ordered = $this->orderSections($data['sections'] ?? [], $data['gondola']['flow'] ?? 'left_to_right');
        $alignment = $data['gondola']['alignment'] ?? 'justify';

        // Total e índice visual são calculados sobre a lista COMPLETA (antes do
        // filtro), para a barra "Posição do Fluxo" mostrar todos os módulos e
        // destacar o correto — igual ao PdfModulePage.vue (props.total/index).
        $total = count($ordered);

        $entries = [];
        foreach ($ordered as $index => $section) {
            $entries[] = ['index' => $index, 'section' => $section];
        }

        if (! empty($sectionIds)) {
            $entries = array_values(array_filter(
                $entries,
                fn ($entry) => in_array($entry['section']['id'], $sectionIds, true),
            ));
        }

        $pages = [];

        foreach ($entries as $entry) {
            $section = $entry['section'];
            $index = $entry['index'];

            $cremCm = $this->cremalheiraWidth($section);
            $totalWidthCm = (float) ($section['width'] ?? 0) + $cremCm * 2;
            // Folga de topo: no mínimo TOP_HEADROOM_CM, aumentada o quanto for
            // preciso para o produto mais alto da prateleira de cima não
            // estourar acima do topo do módulo (o fitScale encolhe p/ caber).
            $headroomCm = $this->calculateRequiredHeadroom($section, self::TOP_HEADROOM_CM);
            $heightCm = (float) ($section['height'] ?? self::DEFAULT_HEIGHT) + $headroomCm;

            $pxPerCm = $this->fitScale($totalWidthCm, $heightCm, self::COL_CONTENT_WIDTH, self::COL_CONTENT_HEIGHT);

            $module = $this->buildModule($section, $index, $pxPerCm, $alignment, $headroomCm);
            $module['left'] = 0.0;
            // No modo "por módulo" cada módulo é uma página isolada, então
            // SEMPRE mostra as duas cremalheiras (não há rail compartilhada).
            $module['showLeftCremalheira'] = true;
            // Posição no fluxo (1-based) e total, para a barra lateral.
            $module['index'] = $index;
            $module['position'] = $index + 1;
            $module['total'] = $total;
            $pages[] = $module;
        }

        return $pages;
    }

    /**
     * Ordena os módulos na mesma ordem visual do editor: por `ordering` asc e,
     * quando o fluxo é da direita para a esquerda, invertido.
     *
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array<string, mixed>>
     */
    private function orderSections(array $sections, string $flow): array
    {
        $sections = array_values(array_filter(
            $sections,
            fn ($section) => empty($section['deleted_at']),
        ));

        usort($sections, fn ($a, $b) => ($a['ordering'] ?? 0) <=> ($b['ordering'] ?? 0));

        if ($flow === 'right_to_left') {
            $sections = array_reverse($sections);
        }

        return $sections;
    }

    /**
     * Constrói o view-model de um módulo (cremalheira + furos + prateleiras +
     * barras + células de produto), tudo em pixels.
     *
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>
     */
    private function buildModule(array $section, int $index, float $pxPerCm, string $alignment, float $extraHeightCm): array
    {
        $cremCm = $this->cremalheiraWidth($section);
        $sectionWidthCm = (float) ($section['width'] ?? 0);
        $heightCm = (float) ($section['height'] ?? self::DEFAULT_HEIGHT);
        $totalWidthCm = $sectionWidthCm + $cremCm * 2;

        $holePositions = $this->calculateHolePositions($section);

        // Prateleiras ordenadas por shelf_position asc (igual ao editor).
        $shelves = array_values(array_filter(
            $section['shelves'] ?? [],
            fn ($shelf) => empty($shelf['deleted_at']),
        ));
        usort($shelves, fn ($a, $b) => ($a['shelf_position'] ?? 0) <=> ($b['shelf_position'] ?? 0));

        // Numeração exibida: prateleiras de cima → maior shelf_position (DESC).
        $shelvesDesc = $shelves;
        usort($shelvesDesc, fn ($a, $b) => ($b['shelf_position'] ?? 0) <=> ($a['shelf_position'] ?? 0));
        $displayNumbers = [];
        foreach ($shelvesDesc as $i => $shelf) {
            $displayNumbers[$shelf['id']] = $i + 1;
        }

        $builtShelves = [];
        $previousShelf = null;

        foreach ($shelves as $shelf) {
            $builtShelves[] = $this->buildShelf(
                $shelf,
                $previousShelf,
                $section,
                $holePositions,
                $sectionWidthCm,
                $cremCm,
                $extraHeightCm,
                $pxPerCm,
                $alignment,
                $displayNumbers[$shelf['id']] ?? 1,
            );
            $previousShelf = $shelf;
        }

        // Cremalheira: furos (px) e base.
        $holeWidthCm = (float) ($section['hole_width'] ?? self::DEFAULT_HOLE_WIDTH);
        $holeHeightCm = (float) ($section['hole_height'] ?? self::DEFAULT_HOLE_HEIGHT);
        $baseHeightCm = (float) ($section['base_height'] ?? self::DEFAULT_BASE_HEIGHT);
        $holes = array_map(fn ($pos) => [
            'top' => round($pos * $pxPerCm, 2),
            'width' => round($holeWidthCm * $pxPerCm, 2),
            'height' => round($holeHeightCm * $pxPerCm, 2),
        ], $holePositions);

        // Profundidade do módulo: maior shelf_depth (fallback base_depth/0).
        $depthCm = 0.0;
        foreach ($shelves as $shelf) {
            $depthCm = max($depthCm, (float) ($shelf['shelf_depth'] ?? 0));
        }

        // Fonte do rótulo "Prat - N" escalada com o módulo, igual ao PdfShelf.vue
        // (Math.max(8, Math.min(16, (10 * scale) / 3))). No PDF, pxPerCm é a
        // escala efetiva, então rótulos ficam maiores no modo "por módulo".
        $shelfLabelPx = round(max(8, min(16, 10 * $pxPerCm / 3)), 1);

        return [
            'ordering' => $section['ordering'] ?? ($index + 1),
            'rawWidthCm' => $sectionWidthCm,
            'rawHeightCm' => $heightCm,
            'rawDepthCm' => $depthCm,
            'shelfLabelPx' => $shelfLabelPx,
            'width' => round($totalWidthCm * $pxPerCm, 2),
            // O módulo é desenhado dentro de uma caixa com a folga de topo.
            'height' => round(($heightCm + $extraHeightCm) * $pxPerCm, 2),
            'sectionWidth' => round($sectionWidthCm * $pxPerCm, 2),
            'sectionHeight' => round($heightCm * $pxPerCm, 2),
            'cremalheiraWidth' => round($cremCm * $pxPerCm, 2),
            'showLeftCremalheira' => $index === 0,
            'extraHeight' => round($extraHeightCm * $pxPerCm, 2),
            'holes' => $holes,
            'baseHeight' => round($baseHeightCm * $pxPerCm, 2),
            'shelves' => $builtShelves,
            'left' => 0.0,
        ];
    }

    /**
     * Calcula a folga de topo (cm) necessária para que NENHUM produto da
     * prateleira de cima "estoure" acima do topo do módulo.
     *
     * Para cada prateleira normal, o topo da pilha de produtos fica em
     * (areaStart + basePosition) - alturaDaPilha, medido a partir do topo da
     * seção (mesma geometria de buildShelf/buildCells). Quando esse valor é
     * negativo o produto ultrapassa o topo da seção; a folga precisa cobrir
     * esse excedente (+ uma pequena folga estética). Retorna no mínimo
     * $minHeadroomCm, preservando a aparência atual quando nada estoura.
     *
     * @param  array<string, mixed>  $section
     */
    private function calculateRequiredHeadroom(array $section, float $minHeadroomCm): float
    {
        $holePositions = $this->calculateHolePositions($section);

        // Prateleiras válidas ordenadas por shelf_position asc (igual buildModule).
        $shelves = array_values(array_filter(
            $section['shelves'] ?? [],
            fn ($shelf) => empty($shelf['deleted_at']),
        ));
        usort($shelves, fn ($a, $b) => ($a['shelf_position'] ?? 0) <=> ($b['shelf_position'] ?? 0));

        $overflowCm = 0.0;
        $previousShelf = null;

        foreach ($shelves as $shelf) {
            $area = $this->calculateShelfArea($shelf, $previousShelf);
            $previousShelf = $shelf;

            // Gancheiras penduram para baixo — nunca estouram para cima.
            if (($shelf['product_type'] ?? 'normal') === 'hook') {
                continue;
            }

            $basePositionCm = $this->shelfBasePosition($shelf, $section, $holePositions, $area['areaStartCm']);
            // Posição da barra (onde o produto se apoia) a partir do topo da seção.
            $barFromTopCm = $area['areaStartCm'] + $basePositionCm;

            // Altura da pilha mais alta da prateleira (empilhamento = rows * altura).
            $tallestStackCm = 0.0;
            foreach ($shelf['segments'] ?? [] as $segment) {
                if (! empty($segment['deleted_at']) || empty($segment['layer']['product'])) {
                    continue;
                }
                $rows = max(1, (int) ($segment['quantity'] ?? 1));
                $productHeightCm = (float) ($segment['layer']['product']['height'] ?? 15);
                $tallestStackCm = max($tallestStackCm, $rows * $productHeightCm);
            }

            // Topo da pilha a partir do topo da seção; se negativo, o excedente
            // vira necessidade de folga.
            $stackTopFromSectionTopCm = $barFromTopCm - $tallestStackCm;
            if ($stackTopFromSectionTopCm < 0) {
                $overflowCm = max($overflowCm, -$stackTopFromSectionTopCm);
            }
        }

        // Pequena folga estética acima do produto mais alto quando há estouro.
        if ($overflowCm > 0) {
            $overflowCm += 2.0;
        }

        return max($minHeadroomCm, $overflowCm);
    }

    /**
     * Constrói o view-model de uma prateleira: área, barra e células.
     *
     * @param  array<string, mixed>  $shelf
     * @param  array<string, mixed>|null  $previousShelf
     * @param  array<string, mixed>  $section
     * @param  array<int, float>  $holePositions
     * @return array<string, mixed>
     */
    private function buildShelf(
        array $shelf,
        ?array $previousShelf,
        array $section,
        array $holePositions,
        float $sectionWidthCm,
        float $cremCm,
        float $extraHeightCm,
        float $pxPerCm,
        string $alignment,
        int $displayNumber,
    ): array {
        $area = $this->calculateShelfArea($shelf, $previousShelf);
        $shelfHeightCm = (float) ($shelf['shelf_height'] ?? 0);
        $isHook = ($shelf['product_type'] ?? 'normal') === 'hook';

        $basePositionCm = $this->shelfBasePosition($shelf, $section, $holePositions, $area['areaStartCm']);

        // Posição da área dentro do módulo (top inclui a folga de topo).
        $areaTopCm = $area['areaStartCm'] + $extraHeightCm;

        $cells = $this->buildCells(
            $shelf,
            $area['areaHeightCm'],
            $basePositionCm,
            $shelfHeightCm,
            $sectionWidthCm,
            $isHook,
            $alignment,
            $pxPerCm,
        );

        return [
            'areaTop' => round($areaTopCm * $pxPerCm, 2),
            'areaLeft' => round($cremCm * $pxPerCm, 2),
            'areaWidth' => round($sectionWidthCm * $pxPerCm, 2),
            'areaHeight' => round($area['areaHeightCm'] * $pxPerCm, 2),
            'barTop' => round($basePositionCm * $pxPerCm, 2),
            'barHeight' => round($shelfHeightCm * $pxPerCm, 2),
            'displayNumber' => $displayNumber,
            'shelfPositionCm' => (float) ($shelf['shelf_position'] ?? 0),
            'cells' => $cells,
            'products' => $this->buildShelfProducts($shelf),
        ];
    }

    /**
     * Monta a lista de produtos de UMA prateleira para a tabela lateral do PDF
     * (código ERP, EAN, marca, nome e nº de frentes). As frentes são somadas
     * por produto distinto: o mesmo produto em vários segmentos/camadas soma
     * suas facings (layer.quantity), reproduzindo a coluna "FRENTES" da tabela
     * de produtos da prateleira.
     *
     * @param  array<string, mixed>  $shelf
     * @return array<int, array{codigo_erp: string, ean: string, name: string, brand: string, frentes: int}>
     */
    private function buildShelfProducts(array $shelf): array
    {
        $products = [];

        foreach ($shelf['segments'] ?? [] as $segment) {
            if (! empty($segment['deleted_at']) || empty($segment['layer']['product'])) {
                continue;
            }

            $product = $segment['layer']['product'];
            $facings = max(1, (int) ($segment['layer']['quantity'] ?? 1));

            // Chave de agregação: id do produto (fallback para EAN/código).
            $key = $product['id'] ?? ($product['ean'] ?? ($product['codigo_erp'] ?? $product['name'] ?? ''));

            if (isset($products[$key])) {
                $products[$key]['frentes'] += $facings;

                continue;
            }

            $products[$key] = [
                'codigo_erp' => (string) ($product['codigo_erp'] ?? ''),
                'ean' => (string) ($product['ean'] ?? ''),
                'name' => (string) ($product['name'] ?? ''),
                'brand' => (string) ($product['brand'] ?? ''),
                'frentes' => $facings,
            ];
        }

        return array_values($products);
    }

    /**
     * Distribui as células de produto da prateleira em posição absoluta,
     * replicando o flexbox do PdfShelf/PdfSegment/PdfLayer:
     * - facings (layer.quantity) lado a lado horizontalmente;
     * - empilhamento (segment.quantity) verticalmente;
     * - modo "justify" (padrão): gap uniforme entre todas as frentes e bordas.
     *
     * Para prateleiras normais as células ancoram pela BASE (crescem p/ cima);
     * para gancheiras (hook) ancoram pelo TOPO (penduram p/ baixo).
     *
     * @param  array<string, mixed>  $shelf
     * @return array<int, array<string, mixed>>
     */
    private function buildCells(
        array $shelf,
        float $areaHeightCm,
        float $basePositionCm,
        float $shelfHeightCm,
        float $sectionWidthCm,
        bool $isHook,
        string $alignment,
        float $pxPerCm,
    ): array {
        $segments = array_values(array_filter(
            $shelf['segments'] ?? [],
            fn ($segment) => empty($segment['deleted_at']) && ! empty($segment['layer']['product']),
        ));

        if (empty($segments)) {
            return [];
        }

        // Totais para o cálculo do gap (PdfShelf.justifyGap).
        $totalFacings = 0;
        $totalProductsWidthCm = 0.0;
        foreach ($segments as $segment) {
            $facings = max(1, (int) ($segment['layer']['quantity'] ?? 1));
            $productWidthCm = (float) ($segment['layer']['product']['width'] ?? 10);
            $totalFacings += $facings;
            $totalProductsWidthCm += $facings * $productWidthCm;
        }

        $freeSpaceCm = $sectionWidthCm - $totalProductsWidthCm;
        $align = $alignment ?: 'justify';

        // Encaixe horizontal (overflow): quando as frentes somam mais que a
        // largura da seção (permitido no editor via Shift), encolhe SÓ na
        // horizontal para a fileira caber exatamente em $sectionWidthCm. A
        // altura física do produto é preservada (apenas "achata" a largura).
        $fit = 1.0;
        if ($totalProductsWidthCm > $sectionWidthCm && $sectionWidthCm > 0) {
            $fit = $sectionWidthCm / $totalProductsWidthCm;
        }

        // Define gap entre frentes e o x inicial conforme o alinhamento.
        [$gapCm, $startXCm] = $this->resolveDistribution(
            $align,
            $freeSpaceCm,
            $totalFacings,
            $totalProductsWidthCm,
            $sectionWidthCm,
        );

        // Origem vertical (em cm, dentro da área):
        // - normal: bottom da pilha = (areaHeight - basePosition) a partir do fundo;
        // - hook:   top da pilha = basePosition + shelfHeight a partir do topo.
        $containerBottomCm = $areaHeightCm - $basePositionCm;
        $containerTopCm = $basePositionCm + $shelfHeightCm;

        $cells = [];
        $xCm = $startXCm;

        foreach ($segments as $segment) {
            $facings = max(1, (int) ($segment['layer']['quantity'] ?? 1));
            $rows = max(1, (int) ($segment['quantity'] ?? 1));
            $product = $segment['layer']['product'];
            $productWidthCm = (float) ($product['width'] ?? 10);
            $productHeightCm = (float) ($product['height'] ?? 15);
            // Largura desenhada após o encaixe horizontal (overflow). Quando
            // não há overflow ($fit == 1) é igual à largura física. A altura
            // nunca é afetada por $fit.
            $drawWidthCm = $productWidthCm * $fit;
            $image = $product['image_url_encoded'] ?? null;
            $name = $product['name'] ?? '';

            for ($f = 0; $f < $facings; $f++) {
                for ($r = 0; $r < $rows; $r++) {
                    $cell = [
                        'left' => round($xCm * $pxPerCm, 2),
                        'width' => round($drawWidthCm * $pxPerCm, 2),
                        'height' => round($productHeightCm * $pxPerCm, 2),
                        'image' => $image,
                        'name' => $name,
                        'anchor' => $isHook ? 'top' : 'bottom',
                    ];

                    if ($isHook) {
                        $cell['top'] = round(($containerTopCm + $r * $productHeightCm) * $pxPerCm, 2);
                    } else {
                        $cell['bottom'] = round(($containerBottomCm + $r * $productHeightCm) * $pxPerCm, 2);
                    }

                    $cells[] = $cell;
                }

                // Avança para a próxima frente (gap uniforme em justify/evenly).
                // Em overflow ($fit < 1) o gap já é 0 e a largura encolhida faz
                // a fileira somar exatamente $sectionWidthCm.
                $xCm += $drawWidthCm + $gapCm;
            }
        }

        return $cells;
    }

    /**
     * Resolve (gap entre frentes, x inicial) conforme o alinhamento, em cm.
     *
     * @return array{0: float, 1: float}
     */
    private function resolveDistribution(
        string $align,
        float $freeSpaceCm,
        int $totalFacings,
        float $totalProductsWidthCm,
        float $sectionWidthCm,
    ): array {
        // Sem espaço livre (overflow): cola tudo à esquerda.
        if ($freeSpaceCm <= 0 || $totalFacings === 0) {
            return [0.0, 0.0];
        }

        return match ($align) {
            'left' => [0.0, 0.0],
            'right' => [0.0, $sectionWidthCm - $totalProductsWidthCm],
            'center' => [0.0, ($sectionWidthCm - $totalProductsWidthCm) / 2],
            // justify / evenly / default: gap uniforme incluindo as bordas.
            default => [
                $freeSpaceCm / ($totalFacings + 1),
                $freeSpaceCm / ($totalFacings + 1),
            ],
        };
    }

    /**
     * Porta de calculateHolePositions (useSectionHoles.ts): posições Y (cm) dos
     * furos da cremalheira, medidas a partir do topo da seção.
     *
     * @param  array<string, mixed>  $section
     * @return array<int, float>
     */
    private function calculateHolePositions(array $section): array
    {
        $height = (float) ($section['height'] ?? self::DEFAULT_HEIGHT);
        $baseHeight = (float) ($section['base_height'] ?? self::DEFAULT_BASE_HEIGHT);
        $holeHeight = (float) ($section['hole_height'] ?? self::DEFAULT_HOLE_HEIGHT);
        $holeSpacing = (float) ($section['hole_spacing'] ?? self::DEFAULT_HOLE_SPACING);

        $availableHeight = max(0, $height - $baseHeight);
        $totalSpaceNeeded = $holeHeight + $holeSpacing;

        if ($totalSpaceNeeded <= 0) {
            return [];
        }

        $holeCount = (int) floor($availableHeight / $totalSpaceNeeded);

        if ($holeCount <= 0) {
            return [];
        }

        $remainingSpace = $availableHeight - $holeCount * $holeHeight - ($holeCount - 1) * $holeSpacing;
        $marginTop = $remainingSpace / 2;

        $positions = [];
        for ($i = 0; $i < $holeCount; $i++) {
            $positions[] = $marginTop + $i * ($holeHeight + $holeSpacing);
        }

        return $positions;
    }

    /**
     * Porta de calculateShelfArea (useShelfAreaCalculation.ts).
     *
     * @param  array<string, mixed>  $shelf
     * @param  array<string, mixed>|null  $previousShelf
     * @return array{areaStartCm: float, areaHeightCm: float, areaEndCm: float}
     */
    private function calculateShelfArea(array $shelf, ?array $previousShelf): array
    {
        $shelfPosition = (float) ($shelf['shelf_position'] ?? 0);
        $shelfHeightCm = (float) ($shelf['shelf_height'] ?? 0);

        $areaStartCm = 0.0;

        if ($previousShelf) {
            $previousEnd = (float) ($previousShelf['shelf_position'] ?? 0) + (float) ($previousShelf['shelf_height'] ?? 0);
            $areaStartCm = $previousEnd;

            $maxStart = $shelfPosition - self::MIN_SPACING;

            if ($areaStartCm > $maxStart) {
                $areaStartCm = max($shelfHeightCm, $maxStart);
            }
        } else {
            $areaStartCm = max($shelfHeightCm, $shelfPosition - self::MIN_AREA_HEIGHT);
        }

        $areaEndCm = $shelfPosition + $shelfHeightCm;
        $areaHeightCm = $areaEndCm - $areaStartCm;

        if ($areaHeightCm < self::MIN_AREA_HEIGHT) {
            $newStart = max(0, $areaEndCm - self::MIN_AREA_HEIGHT);

            if ($previousShelf) {
                $previousEnd = (float) ($previousShelf['shelf_position'] ?? 0) + (float) ($previousShelf['shelf_height'] ?? 0);
                $areaStartCm = max($previousEnd, $newStart);
            } else {
                $areaStartCm = $newStart;
            }

            $areaHeightCm = $areaEndCm - $areaStartCm;
        }

        return [
            'areaStartCm' => $areaStartCm,
            'areaHeightCm' => $areaHeightCm,
            'areaEndCm' => $areaEndCm,
        ];
    }

    /**
     * Porta de shelfBasePosition (PdfShelf.vue): deslocamento (cm) da barra da
     * prateleira a partir do topo da área. Centraliza no furo mais próximo
     * quando há furos; senão usa shelf_position - areaStart.
     *
     * @param  array<string, mixed>  $shelf
     * @param  array<string, mixed>  $section
     * @param  array<int, float>  $holePositions
     */
    private function shelfBasePosition(array $shelf, array $section, array $holePositions, float $areaStartCm): float
    {
        $shelfPositionCm = (float) ($shelf['shelf_position'] ?? 0);
        $shelfHeightCm = (float) ($shelf['shelf_height'] ?? 0);

        if (empty($holePositions)) {
            return $shelfPositionCm - $areaStartCm;
        }

        $holeHeight = (float) ($section['hole_height'] ?? self::DEFAULT_HOLE_HEIGHT);

        $closestHolePos = $holePositions[0];
        $minDistance = abs($shelfPositionCm - $holePositions[0]);

        foreach ($holePositions as $pos) {
            $distance = abs($shelfPositionCm - $pos);
            if ($distance < $minDistance) {
                $minDistance = $distance;
                $closestHolePos = $pos;
            }
        }

        $centeredPosition = $closestHolePos + ($holeHeight - $shelfHeightCm) / 2;

        return $centeredPosition - $areaStartCm;
    }

    /**
     * Largura da cremalheira (cm), com fallback ao default.
     *
     * @param  array<string, mixed>  $section
     */
    private function cremalheiraWidth(array $section): float
    {
        $value = $section['cremalheira_width'] ?? self::DEFAULT_CREMALHEIRA_WIDTH;

        return (float) ($value ?: self::DEFAULT_CREMALHEIRA_WIDTH);
    }

    /**
     * Calcula a escala (px por cm) que faz o conteúdo caber tanto na largura
     * quanto na altura da caixa de conteúdo informada.
     */
    private function fitScale(float $widthCm, float $heightCm, float $boxWidthPx, float $boxHeightPx): float
    {
        if ($widthCm <= 0 || $heightCm <= 0) {
            return 1.0;
        }

        return min($boxWidthPx / $widthCm, $boxHeightPx / $heightCm);
    }
}
