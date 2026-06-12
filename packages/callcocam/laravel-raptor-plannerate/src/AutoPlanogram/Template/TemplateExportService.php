<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Template;

use Callcocam\LaravelRaptorPlannerate\Enums\BrandExposure;
use Callcocam\LaravelRaptorPlannerate\Enums\FlavorExposure;
use Callcocam\LaravelRaptorPlannerate\Enums\PriceOrder;
use Callcocam\LaravelRaptorPlannerate\Enums\SizeOrder;
use Callcocam\LaravelRaptorPlannerate\Enums\SpaceFallback;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramSubtemplate;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplate;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TemplateExportService
{
    public function exportTemplate(PlanogramTemplate $template): StreamedResponse
    {
        $template->loadMissing(['subtemplates.slots.category']);

        $spreadsheet = $this->buildSpreadsheet(collect([$template]));
        $filename = 'template_'.$template->code.'_'.now()->format('Y-m-d').'.xlsx';

        return $this->streamResponse($spreadsheet, $filename);
    }

    public function exportAll(string $tenantId, string $search = ''): StreamedResponse
    {
        $templates = PlanogramTemplate::with(['subtemplates.slots.category'])
            ->where('tenant_id', $tenantId)
            ->when($search !== '', fn ($q) => $q->where(function ($w) use ($search): void {
                $w->where('code', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%')
                    ->orWhere('department', 'like', '%'.$search.'%');
            }))
            ->orderBy('code')
            ->get();

        $spreadsheet = $this->buildSpreadsheet($templates);
        $filename = 'templates_'.now()->format('Y-m-d').'.xlsx';

        return $this->streamResponse($spreadsheet, $filename);
    }

    /** @param Collection<int, PlanogramTemplate> $templates */
    private function buildSpreadsheet(Collection $templates): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Templates');

        // Colunas A–P: formato legado (labels PT-BR). Colunas Q–AB: campos novos com
        // valores brutos dos enums, para round-trip simétrico export → import.
        $headers = [
            'A' => 'Código template',
            'B' => 'Departamento',
            'C' => 'Código subtemplate',
            'D' => 'Quantidade de módulos',
            'E' => 'Módulo',
            'F' => 'Posição prateleira',
            'G' => 'Categoria (caminho)',
            'H' => '',
            'I' => 'Categoria (nome)',
            'J' => 'Frentes por SKU',
            'K' => 'Ordem preço',
            'L' => 'Ordem tamanho',
            'M' => 'Tipo de exposição por marca',
            'N' => 'Tipo de exposição por fragrancia ou sabor',
            'O' => 'Se faltar espaço, oque fazer?',
            'P' => 'Usar estoque alvo?',
            'Q' => 'Frentes máximas',
            'R' => 'Prioridade',
            'S' => 'Expansão de frentes',
            'T' => 'Papel da categoria (override)',
            'U' => 'Máx % por SKU',
            'V' => 'Máx % por marca',
            'W' => 'Máx % por subcategoria',
            'X' => 'Critérios visuais (JSON)',
            'Y' => 'Disposição (subtemplate)',
            'Z' => 'Sentido de leitura (subtemplate)',
            'AA' => 'Zona quente (subtemplate)',
            'AB' => 'Zona fria (subtemplate)',
        ];

        foreach ($headers as $col => $label) {
            $sheet->setCellValue($col.'1', $label);
        }

        $headerRange = 'A1:AB1';
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $row = 2;
        foreach ($templates as $template) {
            foreach ($template->subtemplates as $subtemplate) {
                $subtemplateSettings = $this->subtemplateSettingsColumns($subtemplate);

                if ($subtemplate->slots->isEmpty()) {
                    // Linha sem slots ainda carrega as configurações globais do subtemplate (Y–AB)
                    $sheet->fromArray([
                        $template->code,
                        $template->department,
                        $subtemplate->code,
                        $subtemplate->num_modules,
                        '', '', '', '', '', '', '', '', '', '', '', '',
                        '', '', '', '', '', '', '', '',
                        ...$subtemplateSettings,
                    ], null, 'A'.$row);
                    $row++;

                    continue;
                }

                foreach ($subtemplate->slots as $slot) {
                    $catName = $slot->category?->name ?? '';
                    $catPath = $slot->category?->full_path ?? $catName;

                    $sheet->fromArray([
                        $template->code,
                        $template->department,
                        $subtemplate->code,
                        $subtemplate->num_modules,
                        $slot->module_number,
                        $slot->shelf_order,
                        $catPath,
                        '',
                        $catName,
                        $slot->min_facings,
                        $this->priceOrderLabel($slot->price_order),
                        $this->sizeOrderLabel($slot->size_order),
                        $this->exposureLabel($slot->brand_exposure),
                        $this->exposureLabel($slot->flavor_exposure),
                        $this->spaceFallbackLabel($slot->space_fallback),
                        $slot->use_target_stock ? 'Sim' : 'Não',
                        $slot->max_facings,
                        $slot->priority,
                        $slot->facing_expansion?->value ?? '',
                        $slot->role_override?->value ?? '',
                        $slot->max_share_per_sku ?? '',
                        $slot->max_share_per_brand ?? '',
                        $slot->max_share_per_subcategory ?? '',
                        $slot->visual_criteria !== null ? json_encode($slot->visual_criteria, JSON_UNESCAPED_UNICODE) : '',
                        ...$subtemplateSettings,
                    ], null, 'A'.$row);
                    $row++;
                }
            }
        }

        for ($colIndex = 1; $colIndex <= 28; $colIndex++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colIndex))->setAutoSize(true);
        }

        return $spreadsheet;
    }

    /**
     * Configurações globais do subtemplate nas colunas Y–AB (valores brutos dos
     * enums; vazio = não configurado). Repetidas em todas as linhas do subtemplate.
     *
     * @return list<string>
     */
    private function subtemplateSettingsColumns(PlanogramSubtemplate $subtemplate): array
    {
        return [
            $subtemplate->layout_orientation?->value ?? '',
            $subtemplate->flow_direction?->value ?? '',
            $subtemplate->hot_zone_priority?->value ?? '',
            $subtemplate->cold_zone_priority?->value ?? '',
        ];
    }

    private function streamResponse(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        return new StreamedResponse(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    private function priceOrderLabel(PriceOrder $order): string
    {
        return match ($order) {
            PriceOrder::Desc => 'Do mais caro para o mais barato',
            PriceOrder::Asc => 'Do mais barato',
            PriceOrder::None => '',
        };
    }

    private function sizeOrderLabel(SizeOrder $order): string
    {
        return match ($order) {
            SizeOrder::Desc => 'Do maior para o menor',
            SizeOrder::Asc => 'Do menor',
            SizeOrder::None => '',
        };
    }

    private function exposureLabel(BrandExposure|FlavorExposure $exposure): string
    {
        return ucfirst($exposure->value);
    }

    private function spaceFallbackLabel(SpaceFallback $fallback): string
    {
        return match ($fallback) {
            SpaceFallback::ReduceC => 'Reduzir SKUs curva C',
            SpaceFallback::ReduceFacings => 'Reduzir facings para 1',
            SpaceFallback::RemoveDog => 'Remover retardatários primeiro',
            SpaceFallback::Skip => '',
        };
    }
}
