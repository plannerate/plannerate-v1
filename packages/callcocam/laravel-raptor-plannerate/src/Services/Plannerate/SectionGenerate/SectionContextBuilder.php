<?php

namespace Callcocam\LaravelRaptorPlannerate\Services\Plannerate\SectionGenerate;

use Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\AutoGenerate\RankedProductDTO;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Section;
use Illuminate\Support\Collection;

/**
 * Monta o contexto (section + prateleiras + produtos) para o PlanogramSectionAllocator.
 *
 * O resultado é um texto JSON ou legível enviado como prompt ao Agent.
 */
class SectionContextBuilder
{
    /**
     * Construir o contexto para uma section.
     *
     * @param  Collection<int, RankedProductDTO>  $rankedProducts  Produtos já ranqueados (ex.: ProductSelectionService)
     */
    public function build(Section $section, Collection $rankedProducts): string
    {
        $section->loadMissing('shelves');

        $widthCm = $this->getSectionWidthCm($section);
        $shelves = $this->shelvesToArray($section);
        $products = $this->productsToArray($rankedProducts);

        $context = [
            'section' => [
                'id' => $section->id,
                'width_cm' => round($widthCm, 2),
            ],
            'shelves' => $shelves,
            'products' => $products,
        ];

        return "Allocate the following products to the shelves. Return only valid shelf_id and product_id from this context.\n\n".
            json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Largura disponível da section (cm). Usa width direto para não depender de gondola.
     */
    protected function getSectionWidthCm(Section $section): float
    {
        $width = $section->width ?? 0;

        if ($width > 0) {
            return (float) $width;
        }

        if ($section->relationLoaded('gondola') && $section->gondola) {
            return (float) ($section->section_width ?? 100);
        }

        return 100.0;
    }

    /**
     * @return array<int, array{id: string, height_cm: float}>
     */
    protected function shelvesToArray(Section $section): array
    {
        $list = [];
        foreach ($section->shelves as $shelf) {
            $list[] = [
                'id' => $shelf->id,
                'height_cm' => (float) ($shelf->shelf_height ?? $shelf->height ?? 30),
            ];
        }

        return $list;
    }

    /**
     * @param  Collection<int, RankedProductDTO>  $rankedProducts
     * @return array<int, array{id: string, name: string, width_cm: float, height_cm: float, score: float, abc_class: string|null}>
     */
    protected function productsToArray(Collection $rankedProducts): array
    {
        $list = [];
        foreach ($rankedProducts as $dto) {
            $p = $dto->product;
            $list[] = [
                'id' => $p->id,
                'name' => $p->name ?? '',
                'width_cm' => (float) ($p->width ?? 10),
                'height_cm' => (float) ($p->height ?? 25),
                'score' => round($dto->score, 2),
                'abc_class' => $dto->abcClass,
            ];
        }

        return $list;
    }
}
