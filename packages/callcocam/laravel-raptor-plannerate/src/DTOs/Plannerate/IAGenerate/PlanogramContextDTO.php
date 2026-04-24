<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\IAGenerate;

use Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola;

/**
 * DTO de Contexto do Planograma para IA
 * Contém todas as informações estruturadas sobre gôndola, prateleiras e produtos
 */
readonly class PlanogramContextDTO
{
    public function __construct(
        public string $gondolaId,
        public array $gondolaData,
        public array $shelves,
        public array $products,
        public array $categoryHierarchy,
        public array $merchandisingRules,
    ) {}

    /**
     * Criar contexto a partir de uma gôndola
     */
    public static function fromGondola(
        Gondola $gondola,
        array $products,
        array $categoryHierarchy,
        array $merchandisingRules = []
    ): self {
        // Calcular dimensões da gôndola a partir das sections
        $totalWidth = 0;
        $maxHeight = 0;
        $maxDepth = 0;

        foreach ($gondola->sections as $section) {
            $totalWidth += $section->width ?? $section->base_width ?? 90;
            $sectionHeight = $section->height ?? 200;
            $sectionDepth = $section->base_depth ?? 40;

            $maxHeight = max($maxHeight, $sectionHeight);
            $maxDepth = max($maxDepth, $sectionDepth);
        }

        // Extrair dados da gôndola
        $gondolaData = [
            'id' => $gondola->id,
            'name' => $gondola->name,
            'width' => $totalWidth ?: 360, // 4 módulos de 90cm
            'height' => $maxHeight ?: 200,
            'depth' => $maxDepth ?: 40,
            'num_modulos' => $gondola->num_modulos ?? count($gondola->sections),
        ];

        // Extrair dados das prateleiras (sections -> shelves)
        $shelves = [];
        foreach ($gondola->sections as $section) {
            // Dimensões da section (usadas como fallback para shelf)
            $sectionWidth = $section->width ?? $section->base_width ?? 90;
            $sectionDepth = $section->base_depth ?? 40;

            foreach ($section->shelves as $shelf) {
                // Usar dimensões da shelf apenas se forem válidas (> 10cm)
                // Caso contrário, herdar da section
                $shelfWidth = ($shelf->shelf_width && $shelf->shelf_width > 10)
                    ? $shelf->shelf_width
                    : $sectionWidth;
                $shelfHeight = ($shelf->shelf_height && $shelf->shelf_height > 10)
                    ? $shelf->shelf_height
                    : 30;
                $shelfDepth = ($shelf->shelf_depth && $shelf->shelf_depth > 10)
                    ? $shelf->shelf_depth
                    : $sectionDepth;

                $shelves[] = [
                    'id' => $shelf->id,
                    'section_id' => $section->id,
                    'section_name' => $section->name,
                    'position' => $shelf->shelf_position,
                    'width' => $shelfWidth,
                    'height' => $shelfHeight,
                    'depth' => $shelfDepth,
                    'max_weight' => $shelf->max_weight ?? 50,
                    'available_space' => $shelfWidth * $shelfDepth, // área disponível em cm²
                ];
            }
        }

        // Formatar produtos para contexto da IA
        $productsFormatted = array_map(function ($product) {
            return [
                'id' => $product['id'],
                'name' => $product['name'],
                'ean' => $product['ean'] ?? null,
                'brand' => $product['brand'] ?? null,
                'category' => $product['category'] ?? null,
                'subcategory' => $product['subcategory'] ?? null,
                'dimensions' => [
                    'width' => $product['width'] ?? 0,
                    'height' => $product['height'] ?? 0,
                    'depth' => $product['depth'] ?? 0,
                ],
                'weight' => $product['weight'] ?? 0,
                'score' => $product['score'] ?? 0,
                'abc_class' => $product['abc_class'] ?? 'C',
                'suggested_facings' => $product['suggested_facings'] ?? 1,
                'target_stock' => $product['target_stock'] ?? null,
            ];
        }, $products);

        return new self(
            gondolaId: $gondola->id,
            gondolaData: $gondolaData,
            shelves: $shelves,
            products: $productsFormatted,
            categoryHierarchy: $categoryHierarchy,
            merchandisingRules: $merchandisingRules,
        );
    }

    /**
     * Converter para array
     */
    public function toArray(): array
    {
        return [
            'gondola_id' => $this->gondolaId,
            'gondola' => $this->gondolaData,
            'shelves' => $this->shelves,
            'products' => $this->products,
            'category_hierarchy' => $this->categoryHierarchy,
            'merchandising_rules' => $this->merchandisingRules,
        ];
    }

    /**
     * Gerar resumo estatístico para o prompt
     */
    public function getStatsSummary(): array
    {
        $totalShelfArea = array_sum(array_column($this->shelves, 'available_space'));
        $totalProductArea = array_sum(array_map(function ($product) {
            return ($product['dimensions']['width'] ?? 0) *
                   ($product['dimensions']['depth'] ?? 0) *
                   ($product['suggested_facings'] ?? 1);
        }, $this->products));

        $abcDistribution = array_count_values(array_column($this->products, 'abc_class'));

        return [
            'total_shelves' => count($this->shelves),
            'total_shelf_area_cm2' => round($totalShelfArea, 2),
            'total_products' => count($this->products),
            'total_product_area_cm2' => round($totalProductArea, 2),
            'space_utilization_estimate' => round(($totalProductArea / max($totalShelfArea, 1)) * 100, 2).'%',
            'abc_distribution' => $abcDistribution,
            'avg_product_score' => round(array_sum(array_column($this->products, 'score')) / max(count($this->products), 1), 2),
        ];
    }
}
