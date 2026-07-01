<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export;

use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Jobs\GenerateGondolaReportJob;
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
            'icons' => $this->pdfIcons(),
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
     * Ícones (lucide) usados nos cabeçalhos/rodapé do PDF, embutidos em base64.
     *
     * O dompdf NÃO renderiza SVG inline, então os mesmos ícones usados nos
     * componentes Vue (PdfGondolaHeader/PdfPageFooter) são pré-rasterizados como
     * PNG em `resources/views/pdf/icons/` (verde = primary; branco = rodapé) e
     * embutidos como data-URI, igual à logo. Retorna name => data-URI (ou null).
     *
     * @return array<string, string|null>
     */
    protected function pdfIcons(): array
    {
        $dir = dirname(__DIR__, 4).'/resources/views/pdf/icons';

        $names = [
            'building-2', 'layout-grid', 'store', 'package', 'layers',
            'calendar-days', 'user', 'arrow-right', 'clipboard-list-white',
        ];

        $icons = [];

        foreach ($names as $name) {
            $path = $dir.'/'.$name.'.png';
            $icons[$name] = is_file($path)
                ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($path))
                : null;
        }

        return $icons;
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
     * Enfileira a geração de um relatório e devolve para a tela anterior.
     *
     * A geração é pesada (percorre seções → prateleiras → segmentos → layers →
     * produtos, 6.000+ itens em gôndolas grandes), então roda em fila. Ao concluir,
     * o job notifica o usuário (tela + database) com o link de download.
     *
     * @param  string  $format  excel|pdf|compra|dimensao|image
     */
    private function queueReport(string $gondolaId, string $format)
    {
        GenerateGondolaReportJob::dispatch(
            $gondolaId,
            $format,
            (string) auth()->id(),
            (string) Tenant::current()?->getKey(),
        );

        // Consumido via router.post do Inertia → back() com flash de sucesso.
        return back()->with('success', __('plannerate.reports.queued'));
    }

    /**
     * Enfileira o relatório de reposição em formato Excel.
     */
    public function generateExcelReport(string $gondolaId)
    {
        return $this->queueReport($gondolaId, 'excel');
    }

    /**
     * Enfileira o relatório de reposição em formato PDF.
     */
    public function generatePdfReport(string $gondolaId)
    {
        return $this->queueReport($gondolaId, 'pdf');
    }

    /**
     * Enfileira o relatório de compra em formato Excel.
     */
    public function generateCompraReport(string $gondolaId)
    {
        return $this->queueReport($gondolaId, 'compra');
    }

    /**
     * Enfileira o relatório de dimensão em formato Excel.
     */
    public function generateDimensaoReport(string $gondolaId)
    {
        return $this->queueReport($gondolaId, 'dimensao');
    }

    /**
     * Enfileira o relatório de imagem em formato Excel.
     */
    public function generateImageReport(string $gondolaId)
    {
        return $this->queueReport($gondolaId, 'image');
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
