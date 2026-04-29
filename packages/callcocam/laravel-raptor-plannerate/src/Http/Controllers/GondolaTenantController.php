<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers;

use Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola;
use Inertia\Inertia;
use Inertia\Response;

class GondolaTenantController extends Controller
{
    public function show(string $gondolaId): Response
    {
        $gondola = Gondola::with([
            'sections.shelves.segments.layer.product',
        ])->findOrFail($gondolaId);

        return Inertia::render('tenant/gondola/show', [
            'gondola' => $gondola,
            'statistics' => $this->calculateStatistics($gondola),
            'readOnly' => true,
        ]);
    }

    public function showSection(string $gondolaId, string $sectionId): Response
    {
        $gondola = Gondola::with([
            'sections.shelves.segments.layer.product',
        ])->findOrFail($gondolaId);

        $section = $gondola->sections()->findOrFail($sectionId);

        return Inertia::render('tenant/gondola/ShowSection', [
            'gondola' => $gondola,
            'section' => $section,
            'readOnly' => true,
        ]);
    }

    /**
     * @return array<string, float|int>
     */
    protected function calculateStatistics(Gondola $gondola): array
    {
        $totalProducts = 0;
        $totalFacings = 0;
        $occupiedSpace = 0;
        $emptySegments = 0;

        foreach ($gondola->sections as $section) {
            foreach ($section->shelves as $shelf) {
                foreach ($shelf->segments as $segment) {
                    if ($segment->layer && $segment->layer->product) {
                        $totalProducts++;
                        $totalFacings += $segment->quantity ?? 1;

                        $product = $segment->layer->product;
                        if ($product && ($product->width || $product->height)) {
                            $occupiedSpace += ($product->width ?? 0) * ($product->height ?? 0) * ($segment->quantity ?? 1);
                        }
                    } else {
                        $emptySegments++;
                    }
                }
            }
        }

        $totalGondolaSpace = $gondola->width * $gondola->height;
        $occupancyRate = $totalGondolaSpace > 0 ? ($occupiedSpace / $totalGondolaSpace) * 100 : 0;

        return [
            'total_products' => $totalProducts,
            'total_facings' => $totalFacings,
            'occupancy_rate' => round($occupancyRate, 2),
            'total_sections' => $gondola->sections->count(),
            'empty_segments' => $emptySegments,
            'total_space' => $totalGondolaSpace,
            'occupied_space' => round($occupiedSpace, 2),
        ];
    }
}
