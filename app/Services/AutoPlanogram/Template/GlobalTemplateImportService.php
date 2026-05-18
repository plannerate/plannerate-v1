<?php

namespace App\Services\AutoPlanogram\Template;

use App\Models\GlobalPlanogramSubtemplate;
use App\Models\GlobalPlanogramTemplate;
use App\Models\GlobalPlanogramTemplateProduct;
use App\Models\GlobalPlanogramTemplateSlot;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class GlobalTemplateImportService
{
    public function import(string $filePath, string $createdBy): TemplateImportReport
    {
        $spreadsheet = IOFactory::load($filePath);
        $report = new TemplateImportReport;

        DB::connection('landlord')->transaction(function () use ($spreadsheet, $createdBy, $report): void {
            $templatesSheet = $spreadsheet->getSheetByName('Templates');
            $productsSheet = $spreadsheet->getSheetByName('Produtos');

            if ($templatesSheet === null) {
                $report->addError('Aba "Templates" não encontrada no arquivo.');

                return;
            }

            if ($productsSheet === null) {
                $report->addError('Aba "Produtos" não encontrada no arquivo.');

                return;
            }

            $this->importTemplates($templatesSheet, $createdBy, $report);
            $this->importProducts($productsSheet, $report);
        });

        return $report;
    }

    private function normalizeGrouping(string $value): string
    {
        return (string) preg_replace('/\s+/', ' ', strtolower(trim($value)));
    }

    private function importTemplates(Worksheet $sheet, string $createdBy, TemplateImportReport $report): void
    {
        $rows = $sheet->toArray(null, true, true, true);

        $grouped = [];
        foreach ($rows as $rowIndex => $row) {
            if ($rowIndex === 1) {
                continue;
            }

            $templateCode = trim((string) ($row['A'] ?? ''));
            if ($templateCode === '') {
                continue;
            }

            $subtemplateCode = trim((string) ($row['C'] ?? ''));
            $grouped[$templateCode][$subtemplateCode][] = $row;
        }

        foreach ($grouped as $templateCode => $subtemplates) {
            $firstSubtemplate = reset($subtemplates);
            $firstRow = reset($firstSubtemplate);

            $template = GlobalPlanogramTemplate::updateOrCreate(
                ['code' => $templateCode],
                [
                    'name' => $templateCode,
                    'department' => trim((string) ($firstRow['B'] ?? '')),
                    'is_active' => true,
                    'created_by' => $createdBy,
                ],
            );

            $report->templatesCreated++;

            foreach ($subtemplates as $subtemplateCode => $slots) {
                $numModules = (int) ($slots[0]['D'] ?? 0);

                $subtemplate = GlobalPlanogramSubtemplate::updateOrCreate(
                    ['template_id' => $template->getKey(), 'num_modules' => $numModules],
                    [
                        'template_id' => $template->getKey(),
                        'code' => $subtemplateCode,
                        'num_modules' => $numModules,
                        'is_active' => true,
                    ],
                );

                $report->subtemplatesCreated++;

                GlobalPlanogramTemplateSlot::where('subtemplate_id', $subtemplate->getKey())->delete();

                $slotOrdering = 1;
                foreach ($slots as $row) {
                    $grouping = trim((string) ($row['I'] ?? ''));
                    if ($grouping === '') {
                        continue;
                    }

                    GlobalPlanogramTemplateSlot::create([
                        'subtemplate_id' => $subtemplate->getKey(),
                        'module_number' => (int) ($row['E'] ?? 1),
                        'shelf_order' => (int) ($row['F'] ?? 1),
                        'category' => trim((string) ($row['G'] ?? '')),
                        'subcategory' => trim((string) ($row['H'] ?? '')),
                        'grouping' => $grouping,
                        'grouping_normalized' => $this->normalizeGrouping($grouping),
                        'min_facings' => max(1, (int) ($row['J'] ?? 1)),
                        'price_order' => $this->parsePriceOrder((string) ($row['K'] ?? '')),
                        'size_order' => $this->parseSizeOrder((string) ($row['L'] ?? '')),
                        'brand_exposure' => $this->parseBrandExposure((string) ($row['M'] ?? '')),
                        'flavor_exposure' => $this->parseFlavorExposure((string) ($row['N'] ?? '')),
                        'space_fallback' => $this->parseSpaceFallback((string) ($row['O'] ?? '')),
                        'use_target_stock' => $this->parseBoolean((string) ($row['P'] ?? '')),
                        'ordering' => $slotOrdering++,
                        'priority' => 1,
                    ]);

                    $report->slotsCreated++;
                }
            }
        }
    }

    private function importProducts(Worksheet $sheet, TemplateImportReport $report): void
    {
        $rows = $sheet->toArray(null, true, true, true);

        $templatesByDepartment = GlobalPlanogramTemplate::pluck('id', 'department')->toArray();

        foreach ($rows as $rowIndex => $row) {
            if ($rowIndex === 1) {
                continue;
            }

            $ean = trim((string) ($row['A'] ?? ''));
            if ($ean === '') {
                continue;
            }

            $department = trim((string) ($row['C'] ?? ''));
            $templateId = $templatesByDepartment[$department] ?? null;

            if ($templateId === null) {
                $report->addWarning("Produto EAN {$ean}: departamento '{$department}' sem template correspondente.");

                continue;
            }

            $grouping = trim((string) ($row['F'] ?? ''));

            GlobalPlanogramTemplateProduct::updateOrCreate(
                ['template_id' => $templateId, 'ean' => $ean],
                [
                    'template_id' => $templateId,
                    'ean' => $ean,
                    'description' => trim((string) ($row['B'] ?? '')),
                    'department' => $department,
                    'category' => trim((string) ($row['D'] ?? '')),
                    'subcategory' => trim((string) ($row['E'] ?? '')),
                    'grouping' => $grouping,
                    'grouping_normalized' => $this->normalizeGrouping($grouping),
                    'brand' => trim((string) ($row['G'] ?? '')),
                    'package_type' => trim((string) ($row['H'] ?? '')) ?: null,
                    'package_content' => trim((string) ($row['I'] ?? '')) ?: null,
                ],
            );

            $report->productsImported++;
        }
    }

    private function parsePriceOrder(string $value): string
    {
        $normalized = strtolower(trim($value));
        if (str_contains($normalized, 'caro')) {
            return 'desc';
        }
        if (str_contains($normalized, 'barato')) {
            return 'asc';
        }

        return 'none';
    }

    private function parseSizeOrder(string $value): string
    {
        $normalized = strtolower(trim($value));
        if (str_contains($normalized, 'maior')) {
            return 'desc';
        }
        if (str_contains($normalized, 'menor')) {
            return 'asc';
        }

        return 'none';
    }

    private function parseBrandExposure(string $value): string
    {
        return match (strtolower(trim($value))) {
            'vertical' => 'vertical',
            'horizontal' => 'horizontal',
            default => 'mixed',
        };
    }

    private function parseFlavorExposure(string $value): string
    {
        return match (strtolower(trim($value))) {
            'vertical' => 'vertical',
            'horizontal' => 'horizontal',
            default => 'mixed',
        };
    }

    private function parseSpaceFallback(string $value): string
    {
        $normalized = strtolower(trim($value));
        if (str_contains($normalized, 'curva c') || str_contains($normalized, 'reduzir sku')) {
            return 'reduce_c';
        }
        if (str_contains($normalized, 'facing') || str_contains($normalized, 'frente')) {
            return 'reduce_facings';
        }

        return 'skip';
    }

    private function parseBoolean(string $value): bool
    {
        return strtolower(trim($value)) === 'sim';
    }
}
