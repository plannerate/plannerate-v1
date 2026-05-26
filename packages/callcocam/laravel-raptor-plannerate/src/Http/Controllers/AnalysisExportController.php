<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers;

use Callcocam\LaravelRaptorPlannerate\Services\Plannerate\AnalysisExportService;
use Illuminate\Http\Response;

/**
 * Controller responsável pela exportação de relatórios de análises em CSV.
 * Lê os dados salvos no banco (GondolaAnalysis) e gera o arquivo para download.
 */
class AnalysisExportController extends Controller
{
    public function __construct(
        protected AnalysisExportService $exportService
    ) {}

    /**
     * Exporta o relatório da análise ABC da gôndola em CSV.
     *
     * @route GET /api/editor/gondolas/{gondola}/analysis/abc/export
     */
    public function exportAbcCsv(string $gondola): Response
    {
        return $this->exportService->exportAbcToCsv($gondola);
    }

    /**
     * Exporta o relatório da análise de estoque alvo da gôndola em CSV.
     *
     * @route GET /api/editor/gondolas/{gondola}/analysis/stock/export
     */
    public function exportStockCsv(string $gondola): Response
    {
        return $this->exportService->exportStockToCsv($gondola);
    }
}
