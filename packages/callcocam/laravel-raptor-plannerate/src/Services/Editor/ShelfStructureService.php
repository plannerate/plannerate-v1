<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Services\Editor;

use Callcocam\LaravelRaptorPlannerate\Models\Section;

/**
 * Serviço compartilhado para criação física de prateleiras dentro do envelope de uma seção.
 *
 * Fonte única da matemática de posicionamento de prateleiras: usada tanto na criação manual
 * de gôndolas (GondolaService) quanto na criação dirigida pelo motor no modo automático
 * (AutoPlanogramService). Garante que gôndolas manuais e automáticas tenham estrutura física
 * idêntica, de modo que o TemplatePlacementEngine resolva shelf_order → shelf da mesma forma.
 */
class ShelfStructureService
{
    /**
     * Cria $numShelves prateleiras na seção, distribuídas pelos furos da cremalheira
     * dentro do envelope físico da própria seção (altura, base e furos).
     *
     * As dimensões físicas (altura, base, furos) são lidas da Section; apenas as dimensões
     * padrão de prateleira (largura/espessura/profundidade/tipo) vêm de $shelfDefaults.
     *
     * @param  array{shelf_width?: float|int, shelf_height?: float|int, shelf_depth?: float|int, product_type?: string}  $shelfDefaults
     */
    public function createShelves(Section $section, array $shelfDefaults, int $numShelves): void
    {
        if ($numShelves <= 0) {
            return;
        }

        $availableHeight = (float) (($section->height ?? 0) - ($section->base_height ?? 0));
        $shelfPositions = $this->calculateShelfPositions(
            $availableHeight,
            0,
            $numShelves,
            (float) ($section->hole_spacing ?? 2),
            (float) ($section->hole_height ?? 2)
        );

        foreach ($shelfPositions as $index => $position) {
            $section->shelves()->create([
                'code' => str(uniqid('SHELF-'))->upper(),
                'ordering' => $index + 1,
                'shelf_position' => $position,
                'shelf_width' => $shelfDefaults['shelf_width'] ?? $section->width ?? 4,
                'shelf_height' => $shelfDefaults['shelf_height'] ?? 4,
                'shelf_depth' => $shelfDefaults['shelf_depth'] ?? 40,
                'product_type' => $shelfDefaults['product_type'] ?? 'normal',
            ]);
        }
    }

    /**
     * Distribui $numShelves prateleiras pelos furos disponíveis da cremalheira.
     *
     * @return array<int, float>
     */
    public function calculateShelfPositions(
        float $totalHeight,
        float $baseHeight,
        int $numShelves,
        float $holeSpacing,
        float $holeHeight
    ): array {
        if ($numShelves <= 0) {
            return [];
        }

        $holes = $this->buildHolePositions($baseHeight, $totalHeight, $holeSpacing, $holeHeight);
        if (empty($holes)) {
            return [];
        }

        if ($numShelves === 1) {
            $middle = $holes[(int) floor(count($holes) / 2)];

            return [$middle];
        }

        if ($numShelves === 2) {
            return [$holes[0], $holes[count($holes) - 1]];
        }

        $positions = [$holes[0]];
        $first = $holes[0];
        $last = $holes[count($holes) - 1];
        $step = ($last - $first) / ($numShelves - 1);

        for ($i = 1; $i < $numShelves - 1; $i++) {
            $ideal = $first + ($step * $i);
            $positions[] = $this->closestHole($ideal, $holes);
        }
        $positions[] = $last;
        sort($positions);

        return $positions;
    }

    /**
     * @return array<int, float>
     */
    protected function buildHolePositions(float $baseHeight, float $totalHeight, float $holeSpacing, float $holeHeight): array
    {
        $holes = [];
        $current = $baseHeight;
        while ($current <= $totalHeight) {
            $holes[] = $current;
            $current += $holeSpacing;
        }
        if (end($holes) !== $totalHeight) {
            $holes[] = $totalHeight;
        }

        return $holes;
    }

    /**
     * @param  array<int, float>  $holes
     */
    protected function closestHole(float $ideal, array $holes): float
    {
        $closest = $holes[0];
        $min = abs($ideal - $closest);
        foreach ($holes as $hole) {
            $dist = abs($ideal - $hole);
            if ($dist < $min) {
                $min = $dist;
                $closest = $hole;
            }
        }

        return $closest;
    }
}
