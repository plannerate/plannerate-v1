<?php

namespace App\Services\AutoPlanogram\Template;

use App\Models\PlanogramSubtemplate;
use App\Models\PlanogramTemplate;
use App\Models\PlanogramTemplateSlot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class TemplateImportService
{
    public function __construct(private readonly TemplateSlotService $templateSlotService) {}

    public function import(string $filePath, string $tenantId): TemplateImportReport
    {
        $spreadsheet = IOFactory::load($filePath);
        $report = new TemplateImportReport;

        DB::transaction(function () use ($spreadsheet, $tenantId, $report): void {
            $templatesSheet = $spreadsheet->getSheetByName('Templates');

            if ($templatesSheet === null) {
                $report->addError('Aba "Templates" não encontrada no arquivo.');

                return;
            }

            $this->importTemplates($templatesSheet, $tenantId, $report);
        });

        return $report;
    }

    private function importTemplates(Worksheet $sheet, string $tenantId, TemplateImportReport $report): void
    {
        $rows = $sheet->toArray(null, true, true, true);

        // Group rows by template code then subtemplate code
        $grouped = [];
        foreach ($rows as $rowIndex => $row) {
            if ($rowIndex === 1) {
                continue; // skip header
            }

            $templateCode = trim((string) ($row['A'] ?? ''));
            if ($templateCode === '') {
                continue;
            }

            $subtemplateCode = trim((string) ($row['C'] ?? ''));
            $numModules = (int) ($row['D'] ?? 0);

            $grouped[$templateCode][$subtemplateCode][] = $row;
        }

        foreach ($grouped as $templateCode => $subtemplates) {
            // Use first row of first subtemplate for template-level fields
            $firstSubtemplate = reset($subtemplates);
            $firstRow = reset($firstSubtemplate);

            $template = PlanogramTemplate::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenantId, 'code' => $templateCode],
                [
                    'tenant_id' => $tenantId,
                    'name' => $templateCode,
                    'department' => trim((string) ($firstRow['B'] ?? '')),
                    'is_active' => true,
                ],
            );

            $report->templatesCreated++;

            foreach ($subtemplates as $subtemplateCode => $slots) {
                $numModules = (int) ($slots[0]['D'] ?? 0);

                $subtemplate = PlanogramSubtemplate::withoutGlobalScopes()->updateOrCreate(
                    ['tenant_id' => $tenantId, 'template_id' => $template->getKey(), 'num_modules' => $numModules],
                    [
                        'tenant_id' => $tenantId,
                        'template_id' => $template->getKey(),
                        'code' => $subtemplateCode,
                        'num_modules' => $numModules,
                        'is_active' => true,
                    ],
                );

                $report->subtemplatesCreated++;

                $existingSlotCount = PlanogramTemplateSlot::withoutGlobalScopes()
                    ->where('subtemplate_id', $subtemplate->getKey())
                    ->count();

                $slotOrdering = 1;
                $slotsCreatedForSub = 0;
                $slotsUpdatedForSub = 0;

                foreach ($slots as $row) {
                    $grouping = trim((string) ($row['I'] ?? ''));
                    if ($grouping === '') {
                        continue;
                    }

                    $moduleNumber = (int) ($row['E'] ?? 1);
                    $shelfOrder = (int) ($row['F'] ?? 1);

                    $slot = PlanogramTemplateSlot::withoutGlobalScopes()->updateOrCreate(
                        [
                            'subtemplate_id' => $subtemplate->getKey(),
                            'module_number' => $moduleNumber,
                            'shelf_order' => $shelfOrder,
                        ],
                        [
                            'tenant_id' => $tenantId,
                            'category' => trim((string) ($row['G'] ?? '')),
                            'subcategory' => trim((string) ($row['H'] ?? '')),
                            'grouping' => $grouping,
                            'grouping_normalized' => $this->templateSlotService->normalizeGrouping($grouping),
                            'min_facings' => max(1, (int) ($row['J'] ?? 1)),
                            'price_order' => $this->parsePriceOrder((string) ($row['K'] ?? '')),
                            'size_order' => $this->parseSizeOrder((string) ($row['L'] ?? '')),
                            'brand_exposure' => $this->parseBrandExposure((string) ($row['M'] ?? '')),
                            'flavor_exposure' => $this->parseFlavorExposure((string) ($row['N'] ?? '')),
                            'space_fallback' => $this->parseSpaceFallback((string) ($row['O'] ?? '')),
                            'use_target_stock' => $this->parseBoolean((string) ($row['P'] ?? '')),
                            'ordering' => $slotOrdering,
                            'priority' => 1,
                        ],
                    );

                    if ($slot->wasRecentlyCreated) {
                        $slotsCreatedForSub++;
                        $report->slotsCreated++;
                    } else {
                        $slotsUpdatedForSub++;
                        $report->slotsUpdated++;
                    }

                    $slotOrdering++;
                }

                $slotsPreserved = max(0, $existingSlotCount - $slotsUpdatedForSub);

                Log::info('TemplateImportService: slots processados', [
                    'subtemplate_code' => $subtemplate->code,
                    'slots_criados' => $slotsCreatedForSub,
                    'slots_atualizados' => $slotsUpdatedForSub,
                    'slots_preservados' => $slotsPreserved,
                ]);
            }
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
