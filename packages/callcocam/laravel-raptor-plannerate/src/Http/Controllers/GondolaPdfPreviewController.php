<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers;
 
use Callcocam\LaravelRaptorPlannerate\Models\Editor\GondolaAnalysis;
use Callcocam\LaravelRaptorPlannerate\Services\Printing\GondolaPrintService;
use Inertia\Inertia;
use Inertia\Response;

class GondolaPdfPreviewController extends Controller
{
    public function __construct(
        protected GondolaPrintService $printService
    ) {}

    /**
     * Exibe preview da gôndola usando componentes Vue
     */
    public function show(string $gondolaId): Response
    {
        $data = $this->printService->prepareGondolaData($gondolaId);

        // Carregar análises mais recentes
        $abcAnalysis = GondolaAnalysis::getLatestAbcAnalysis($gondolaId);
        $stockAnalysis = GondolaAnalysis::getLatestStockAnalysis($gondolaId);

        return Inertia::render('tenant/editor/pdfPrintview', [
            'gondola' => $data['gondola'],
            'sections' => $data['sections'],
            'analysis' => [
                'abc' => $abcAnalysis?->toAbcFormattedArray(),
                'stock' => $stockAnalysis?->toStockFormattedArray(),
            ],
        ]);
    }
}
