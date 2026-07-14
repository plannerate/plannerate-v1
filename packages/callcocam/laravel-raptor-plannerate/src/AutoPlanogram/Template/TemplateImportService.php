<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Template;

use App\Models\Category;
use Callcocam\LaravelRaptorPlannerate\Concerns\UsesPlannerateTenantDatabase;
use Callcocam\LaravelRaptorPlannerate\Enums\CategoryRole;
use Callcocam\LaravelRaptorPlannerate\Enums\FacingExpansion;
use Callcocam\LaravelRaptorPlannerate\Enums\FlowDirection;
use Callcocam\LaravelRaptorPlannerate\Enums\LayoutOrientation;
use Callcocam\LaravelRaptorPlannerate\Enums\ZonePriority;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramSubtemplate;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplate;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplateSlot;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class TemplateImportService
{
    use UsesPlannerateTenantDatabase;

    public function __construct(private readonly TemplateSlotService $templateSlotService) {}

    public function import(string $filePath, string $tenantId): TemplateImportReport
    {
        $spreadsheet = IOFactory::load($filePath);
        $report = new TemplateImportReport;

        $this->plannerateTenantDatabase()->transaction(function () use ($spreadsheet, $tenantId, $report): void {
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

            // withoutGlobalScopes() encontra um template excluído com o mesmo código —
            // sem isso ele fica "reimportado" mas continua invisível (soft deleted).
            if ($template->trashed()) {
                $template->restore();
            }

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

                if ($subtemplate->trashed()) {
                    $subtemplate->restore();
                }

                $report->subtemplatesCreated++;

                // Configurações globais do subtemplate (colunas Y–AB, formato novo).
                // Arquivos legados (A–P) não têm essas colunas — não tocamos nos valores salvos.
                $this->applySubtemplateSettings($subtemplate, $slots[0]);

                $existingSlotCount = PlanogramTemplateSlot::withoutGlobalScopes()
                    ->where('subtemplate_id', $subtemplate->getKey())
                    ->count();

                $slotOrdering = 1;
                $slotsCreatedForSub = 0;
                $slotsUpdatedForSub = 0;

                foreach ($slots as $row) {
                    $categoryName = trim((string) ($row['I'] ?? ''));
                    if ($categoryName === '') {
                        continue;
                    }

                    $moduleNumber = (int) ($row['E'] ?? 1);
                    $shelfOrder = (int) ($row['F'] ?? 1);

                    $categoryId = $this->resolveSlotCategory($categoryName, $tenantId);

                    if ($categoryId === null) {
                        $report->slotsWithoutCategory[] = [
                            'category_name' => $categoryName,
                            'module' => $moduleNumber,
                            'shelf_order' => $shelfOrder,
                            'sugestao' => 'Configure manualmente no wizard de template',
                        ];
                    }

                    $slot = PlanogramTemplateSlot::withoutGlobalScopes()->updateOrCreate(
                        [
                            'subtemplate_id' => $subtemplate->getKey(),
                            'module_number' => $moduleNumber,
                            'shelf_order' => $shelfOrder,
                        ],
                        [
                            'tenant_id' => $tenantId,
                            'category_id' => $categoryId,
                            'min_facings' => max(1, (int) ($row['J'] ?? 1)),
                            'price_order' => $this->parsePriceOrder((string) ($row['K'] ?? '')),
                            'size_order' => $this->parseSizeOrder((string) ($row['L'] ?? '')),
                            'brand_exposure' => $this->parseBrandExposure((string) ($row['M'] ?? '')),
                            'flavor_exposure' => $this->parseFlavorExposure((string) ($row['N'] ?? '')),
                            'space_fallback' => $this->parseSpaceFallback((string) ($row['O'] ?? '')),
                            'use_target_stock' => $this->parseBoolean((string) ($row['P'] ?? '')),
                            'ordering' => $slotOrdering,
                            ...$this->extendedSlotFields($row),
                        ],
                    );

                    if ($slot->trashed()) {
                        $slot->restore();
                    }

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

    /**
     * Campos novos do slot (colunas Q–X do formato estendido). Em arquivos legados
     * (A–P) as colunas não existem ou vêm vazias: nesses casos só `priority`
     * recebe o fallback 1 (comportamento original); os demais não são incluídos,
     * preservando defaults do banco na criação e valores existentes no update.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function extendedSlotFields(array $row): array
    {
        $fields = [
            'priority' => $this->intInRange((string) ($row['R'] ?? ''), 1, 10) ?? 1,
        ];

        $maxFacings = $this->intInRange((string) ($row['Q'] ?? ''), 1, 20);
        if ($maxFacings !== null) {
            $fields['max_facings'] = max($maxFacings, max(1, (int) ($row['J'] ?? 1)));
        }

        $facingExpansion = FacingExpansion::tryFrom(strtolower(trim((string) ($row['S'] ?? ''))));
        if ($facingExpansion !== null) {
            $fields['facing_expansion'] = $facingExpansion->value;
        }

        if (array_key_exists('T', $row)) {
            $fields['role_override'] = CategoryRole::tryFrom(strtolower(trim((string) ($row['T'] ?? ''))))?->value;
        }

        foreach (['U' => 'max_share_per_sku', 'V' => 'max_share_per_brand', 'W' => 'max_share_per_subcategory'] as $col => $field) {
            if (array_key_exists($col, $row)) {
                $fields[$field] = $this->intInRange((string) ($row[$col] ?? ''), 1, 100);
            }
        }

        if (array_key_exists('X', $row)) {
            $fields['visual_criteria'] = $this->parseVisualCriteria((string) ($row['X'] ?? ''));
        }

        return $fields;
    }

    /**
     * Configurações globais do subtemplate (colunas Y–AB). Só aplica quando as
     * colunas existem na planilha — arquivos legados não sobrescrevem nada.
     *
     * @param  array<string, mixed>  $row
     */
    private function applySubtemplateSettings(PlanogramSubtemplate $subtemplate, array $row): void
    {
        if (! array_key_exists('Y', $row) && ! array_key_exists('Z', $row)
            && ! array_key_exists('AA', $row) && ! array_key_exists('AB', $row)) {
            return;
        }

        $subtemplate->update([
            'layout_orientation' => LayoutOrientation::tryFrom(strtolower(trim((string) ($row['Y'] ?? ''))))?->value,
            'flow_direction' => FlowDirection::tryFrom(strtolower(trim((string) ($row['Z'] ?? ''))))?->value,
            'hot_zone_priority' => ZonePriority::tryFrom(strtolower(trim((string) ($row['AA'] ?? ''))))?->value,
            'cold_zone_priority' => ZonePriority::tryFrom(strtolower(trim((string) ($row['AB'] ?? ''))))?->value,
        ]);
    }

    /** Inteiro dentro do intervalo, ou null para vazio/inválido/fora do range. */
    private function intInRange(string $value, int $min, int $max): ?int
    {
        $trimmed = trim($value);

        if ($trimmed === '' || ! is_numeric($trimmed)) {
            return null;
        }

        $int = (int) $trimmed;

        return ($int >= $min && $int <= $max) ? $int : null;
    }

    /**
     * Decodifica o JSON de critérios visuais (coluna X). Retorna null para
     * vazio ou JSON inválido — slot volta ao comportamento legado.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function parseVisualCriteria(string $value): ?array
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);

        return is_array($decoded) && $decoded !== [] ? $decoded : null;
    }

    private function resolveSlotCategory(string $categoryName, string $tenantId): ?string
    {
        $parts = explode('|', $categoryName);
        $lastName = mb_strtolower(trim((string) end($parts)), 'UTF-8');

        $category = Category::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereRaw('LOWER(name) = ?', [$lastName])
            ->first(['id']);

        if ($category) {
            return $category->id;
        }

        $fullName = mb_strtolower(trim($categoryName), 'UTF-8');

        $category = Category::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereRaw('LOWER(name) = ?', [$fullName])
            ->first(['id']);

        return $category?->id;
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
        if (str_contains($normalized, 'retardat')) {
            return 'remove_dog';
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
