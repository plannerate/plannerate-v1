<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export;

use Barryvdh\DomPDF\Facade\Pdf;
use Callcocam\LaravelRaptorPlannerate\Exports\GondolaCompraReportExport;
use Callcocam\LaravelRaptorPlannerate\Exports\GondolaDimensaoReportExport;
use Callcocam\LaravelRaptorPlannerate\Exports\GondolaImageReportExport;
use Callcocam\LaravelRaptorPlannerate\Exports\GondolaReportExport;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Services\Export\GondolaPrintService;
use Callcocam\LaravelRaptorPlannerate\Services\Export\PlanogramPdfLayoutService;
use Callcocam\LaravelRaptorPlannerate\Services\Reports\GondolaReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GondolaReportController extends Controller
{
    protected GondolaReportService $gondolaReportService;

    public function __construct(
        GondolaReportService $gondolaReportService,
        protected GondolaPrintService $gondolaPrintService,
        protected PlanogramPdfLayoutService $planogramPdfLayoutService,
    ) {
        $this->gondolaReportService = $gondolaReportService;
    }

    /**
     * Gera o PDF visual da gôndola no modo "em linha" (A4 landscape, todos os
     * módulos lado a lado). Renderizado no servidor com dompdf — substitui o
     * pipeline html2canvas do frontend.
     */
    public function generatePlanogramRowPdf(string $gondolaId, Request $request)
    {
        $data = $this->gondolaPrintService->prepareGondolaData($gondolaId);
        $layout = $this->planogramPdfLayoutService->buildRowLayout($data);

        $pdf = Pdf::loadView('plannerate::pdf.planogram-row', $this->planogramViewData($data, $request) + [
            'layout' => $layout,
        ])
            ->setPaper('a4', 'landscape')
            ->setOptions($this->pdfOptions());

        return $this->respondWithPdf($pdf, $data, $request, 'planograma-linha');
    }

    /**
     * Gera o PDF visual da gôndola no modo "por módulo" (A4 portrait, 1 página
     * por módulo). Aceita `sectionIds` (CSV) para filtrar os módulos exportados.
     */
    public function generatePlanogramModulesPdf(string $gondolaId, Request $request)
    {
        $data = $this->gondolaPrintService->prepareGondolaData($gondolaId);

        $sectionIds = $request->query('sectionIds');
        if (is_string($sectionIds)) {
            $sectionIds = array_values(array_filter(explode(',', $sectionIds)));
        }

        $pages = $this->planogramPdfLayoutService->buildModulesLayout($data, $sectionIds ?: null);

        $pdf = Pdf::loadView('plannerate::pdf.planogram-modules', $this->planogramViewData($data, $request) + [
            'pages' => $pages,
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions($this->pdfOptions());

        return $this->respondWithPdf($pdf, $data, $request, 'planograma-modulos');
    }

    /**
     * Variáveis comuns aos templates de PDF do planograma.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function planogramViewData(array $data, Request $request): array
    {
        $flow = $data['gondola']['flow'] ?? 'left_to_right';

        return [
            'gondola' => $data['gondola'],
            'logo' => $this->brandLogo(),
            'tenantName' => $this->currentTenantName(),
            'responsavel' => $request->user()?->name ?? '',
            'flowLabel' => $flow === 'right_to_left'
                ? __('plannerate.print.preview.right_to_left')
                : __('plannerate.print.preview.left_to_right'),
            'isLeftToRight' => $flow !== 'right_to_left',
            'observacoes' => $data['gondola']['planogram']['description']
                ?? __('plannerate.print.preview.default_observations'),
        ];
    }

    /**
     * Opções do dompdf compartilhadas (imagens remotas + base64 habilitadas).
     *
     * @return array<string, mixed>
     */
    protected function pdfOptions(): array
    {
        return [
            'dpi' => 96,
            // DejaVu Sans (empacotada no dompdf) tem as setas/estrelas do
            // indicador de fluxo (→ ← ★ ☆) e acentos; a sans-serif padrão
            // (Helvetica) renderiza esses glifos como "?".
            'defaultFont' => 'DejaVu Sans',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
        ];
    }

    /**
     * Stream (inline) ou download conforme a query `download`.
     *
     * @param  array<string, mixed>  $data
     */
    protected function respondWithPdf($pdf, array $data, Request $request, string $prefix)
    {
        $slug = $data['gondola']['slug'] ?? $data['gondola']['id'];
        $filename = $prefix.'-'.$slug.'-'.Carbon::now()->format('d-m-Y').'.pdf';

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }

    /**
     * Logo Plannerate embutida em base64 (evita depender de acesso a arquivo
     * pelo dompdf). Retorna null se o arquivo não existir.
     */
    protected function brandLogo(): ?string
    {
        $path = public_path('img/marca-claro.png');

        if (! is_file($path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
    }

    /**
     * Nome do tenant atual (Spatie), resolvido pelo modelo configurado.
     */
    protected function currentTenantName(): string
    {
        $tenantModel = config('multitenancy.tenant_model');

        if (is_string($tenantModel) && method_exists($tenantModel, 'current')) {
            return $tenantModel::current()?->name ?? '';
        }

        return '';
    }

    /**
     * Gera relatório da gôndola em formato Excel
     */
    public function generateExcelReport(string $gondolaId)
    {
        try {
            // Obter dados da gôndola
            $reportData = $this->gondolaReportService->generateReportData($gondolaId);

            if (empty($reportData['products'])) {
                return response()->json([
                    'error' => 'Nenhum produto encontrado na gôndola para gerar relatório.',
                ], 404);
            }

            // Gerar nome do arquivo
            $filename = 'relatorio-reposicao'.'-'.Carbon::now()->format('dmY').'.xlsx';

            // Log da geração
            Log::info('Relatório Excel de gôndola gerado', [
                'gondola_id' => $gondolaId,
                'filename' => $filename,
                'total_products' => count($reportData['products']),
                'gondola_name' => $reportData['gondola_name'] ?? 'N/A',
            ]);

            // Criar e retornar o arquivo Excel usando PhpSpreadsheet puro
            $export = new GondolaReportExport($reportData);

            return $export->download($filename);

        } catch (\Exception $e) {
            Log::error('Erro ao gerar relatório Excel da gôndola:', [
                'gondola_id' => $gondolaId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Erro ao gerar relatório Excel',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Gera relatório da gôndola em formato PDF
     */
    public function generatePdfReport(string $gondolaId)
    {
        try {
            // Obter dados da gôndola
            $reportData = $this->gondolaReportService->generateReportData($gondolaId);

            if (empty($reportData['products'])) {
                return response()->json([
                    'error' => 'Nenhum produto encontrado na gôndola para gerar relatório.',
                ], 404);
            }

            // Gerar PDF
            $pdf = Pdf::loadView('plannerate::gondola-report', $reportData)
                ->setPaper('a4', 'landscape') // Paisagem para tabela larga
                ->setOptions([
                    'dpi' => 150,
                    'defaultFont' => 'sans-serif',
                    'isHtml5ParserEnabled' => true,
                    'isRemoteEnabled' => true,
                ]);

            $filename = 'relatorio-reposicao-'.Carbon::now()->format('d-m-Y').'.pdf';

            // Log da geração
            Log::info('Relatório PDF de gôndola gerado', [
                'gondola_id' => $gondolaId,
                'filename' => $filename,
                'total_products' => count($reportData['products']),
                'gondola_name' => $reportData['gondola_name'] ?? 'N/A',
            ]);

            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('Erro ao gerar relatório PDF da gôndola:', [
                'gondola_id' => $gondolaId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Erro ao gerar relatório PDF',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Gera relatório de compra da gôndola em formato Excel
     */
    public function generateCompraReport(string $gondolaId)
    {
        try {
            // Obter dados da gôndola
            $reportData = $this->gondolaReportService->generateReportData($gondolaId);

            if (empty($reportData['products'])) {
                return response()->json([
                    'error' => 'Nenhum produto encontrado na gôndola para gerar relatório.',
                ], 404);
            }

            // Gerar nome do arquivo
            $filename = 'relatorio-compra-'.Carbon::now()->format('d-m-Y').'.xlsx';

            // Log da geração
            Log::info('Relatório Excel de compra gerado', [
                'gondola_id' => $gondolaId,
                'filename' => $filename,
                'total_products' => count($reportData['products']),
                'gondola_name' => $reportData['gondola_name'] ?? 'N/A',
            ]);

            // Criar e retornar o arquivo Excel
            $export = new GondolaCompraReportExport($reportData);

            return $export->download($filename);

        } catch (\Exception $e) {
            Log::error('Erro ao gerar relatório Excel de compra:', [
                'gondola_id' => $gondolaId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Erro ao gerar relatório de compra',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Gera relatório de dimensão da gôndola em formato Excel
     */
    public function generateDimensaoReport(string $gondolaId)
    {
        try {
            $startTime = microtime(true);
            Log::info('📊 INICIANDO Controller - Relatório Dimensão', [
                'gondola_id' => $gondolaId,
            ]);

            // Obter dados da gôndola
            $reportData = $this->gondolaReportService->generateReportData($gondolaId);

            if (empty($reportData['products'])) {
                return response()->json([
                    'error' => 'Nenhum produto encontrado na gôndola para gerar relatório.',
                ], 404);
            }

            // Gerar nome do arquivo
            $filename = 'relatorio-dimensao-'.Carbon::now()->format('d-m-Y').'.xlsx';

            Log::info('📋 INICIANDO criação do Excel - Dimensão', [
                'gondola_id' => $gondolaId,
                'filename' => $filename,
                'total_products' => count($reportData['products']),
                'gondola_name' => $reportData['gondola_name'] ?? 'N/A',
            ]);

            // Criar e retornar o arquivo Excel
            $export = new GondolaDimensaoReportExport($reportData);
            $response = $export->download($filename);

            $endTime = microtime(true);
            $duration = round(($endTime - $startTime), 2);

            Log::info('🎉 CONCLUÍDO Controller - Relatório Dimensão', [
                'gondola_id' => $gondolaId,
                'total_duration_seconds' => $duration,
            ]);

            return $response;

        } catch (\Exception $e) {
            Log::error('Erro ao gerar relatório Excel de dimensão:', [
                'gondola_id' => $gondolaId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Erro ao gerar relatório de dimensão',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Gera relatório de imagem da gôndola em formato Excel
     */
    public function generateImageReport(string $gondolaId)
    {
        try {
            // Obter dados da gôndola
            $reportData = $this->gondolaReportService->generateReportData($gondolaId);

            if (empty($reportData['products'])) {
                return response()->json([
                    'error' => 'Nenhum produto encontrado na gôndola para gerar relatório.',
                ], 404);
            }

            // Gerar nome do arquivo
            $filename = 'relatorio-image-'.Carbon::now()->format('d-m-Y').'.xlsx';

            // Log da geração
            Log::info('Relatório Excel de imagem gerado', [
                'gondola_id' => $gondolaId,
                'filename' => $filename,
                'total_products' => count($reportData['products']),
                'gondola_name' => $reportData['gondola_name'] ?? 'N/A',
            ]);

            // Criar e retornar o arquivo Excel
            $export = new GondolaImageReportExport($reportData);

            return $export->download($filename);

        } catch (\Exception $e) {
            Log::error('Erro ao gerar relatório Excel de imagem:', [
                'gondola_id' => $gondolaId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Erro ao gerar relatório de imagem',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retorna dados da gôndola para preview (opcional)
     */
    public function getReportData(string $gondolaId)
    {
        try {
            $reportData = $this->gondolaReportService->generateReportData($gondolaId);

            return response()->json($reportData);

        } catch (\Exception $e) {
            Log::error('Erro ao obter dados do relatório da gôndola:', [
                'gondola_id' => $gondolaId,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Erro ao obter dados do relatório',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
