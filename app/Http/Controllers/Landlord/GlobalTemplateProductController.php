<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Concerns\InteractsWithDeferredIndex;
use App\Http\Controllers\Controller;
use App\Models\GlobalPlanogramSubtemplate;
use App\Models\GlobalPlanogramTemplate;
use App\Models\GlobalPlanogramTemplateProduct;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GlobalTemplateProductController extends Controller
{
    use InteractsWithDeferredIndex;

    public function index(Request $request, GlobalPlanogramTemplate $globalPlanogramTemplate): Response
    {
        $this->authorize('view', $globalPlanogramTemplate);

        $globalPlanogramTemplate->load(['subtemplates.slots', 'templateProducts']);

        $searchQuery = $this->requestString($request, 'search');

        return Inertia::render('landlord/planogram-templates/Products', [
            'template' => $this->templateData($globalPlanogramTemplate),
            'products' => $this->productsData($globalPlanogramTemplate),
            'availableGroupings' => $this->availableGroupings($globalPlanogramTemplate),
            'searchResults' => $searchQuery !== '' ? $this->searchProducts($searchQuery) : null,
        ]);
    }

    public function store(Request $request, GlobalPlanogramTemplate $globalPlanogramTemplate): Response
    {
        $this->authorize('update', $globalPlanogramTemplate);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.ean' => ['required', 'string', 'max:50'],
            'items.*.grouping' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.brand' => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($validated['items'] as $item) {
            $ean = $item['ean'];
            $grouping = $item['grouping'];

            $alreadyExists = $globalPlanogramTemplate->templateProducts()
                ->where('ean', $ean)
                ->where('grouping_normalized', $this->normalizeGrouping($grouping))
                ->exists();

            if ($alreadyExists) {
                continue;
            }

            $globalPlanogramTemplate->templateProducts()->create([
                'ean' => $ean,
                'description' => $item['description'] ?? '',
                'department' => $globalPlanogramTemplate->department,
                'brand' => $item['brand'] ?? '',
                'grouping' => $grouping,
                'grouping_normalized' => $this->normalizeGrouping($grouping),
            ]);
        }

        $globalPlanogramTemplate->load('templateProducts');

        return Inertia::render('landlord/planogram-templates/Products', [
            'products' => $this->productsData($globalPlanogramTemplate),
        ]);
    }

    public function update(Request $request, GlobalPlanogramTemplate $globalPlanogramTemplate, GlobalPlanogramTemplateProduct $globalPlanogramTemplateProduct): Response
    {
        $this->authorize('update', $globalPlanogramTemplate);

        $validated = $request->validate([
            'grouping' => ['required', 'string', 'max:255'],
        ]);

        $globalPlanogramTemplateProduct->update([
            'grouping' => $validated['grouping'],
            'grouping_normalized' => $this->normalizeGrouping($validated['grouping']),
        ]);

        $globalPlanogramTemplate->load('templateProducts');

        return Inertia::render('landlord/planogram-templates/Products', [
            'products' => $this->productsData($globalPlanogramTemplate),
        ]);
    }

    public function destroy(GlobalPlanogramTemplate $globalPlanogramTemplate, GlobalPlanogramTemplateProduct $globalPlanogramTemplateProduct): Response
    {
        $this->authorize('update', $globalPlanogramTemplate);

        $globalPlanogramTemplateProduct->delete();

        $globalPlanogramTemplate->load('templateProducts');

        return Inertia::render('landlord/planogram-templates/Products', [
            'products' => $this->productsData($globalPlanogramTemplate),
        ]);
    }

    public function bulkImport(Request $request, GlobalPlanogramTemplate $globalPlanogramTemplate): Response
    {
        $this->authorize('update', $globalPlanogramTemplate);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        // TODO: implement bulk import (Prompt 17)

        $globalPlanogramTemplate->load('templateProducts');

        return Inertia::render('landlord/planogram-templates/Products', [
            'products' => $this->productsData($globalPlanogramTemplate),
        ]);
    }

    public function downloadTemplate(GlobalPlanogramTemplate $globalPlanogramTemplate): never
    {
        $this->authorize('view', $globalPlanogramTemplate);

        // TODO: generate Excel template (Prompt 17)
        abort(501, 'Download de modelo ainda não implementado.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function templateData(GlobalPlanogramTemplate $template): array
    {
        return [
            'id' => $template->id,
            'code' => $template->code,
            'name' => $template->name,
            'department' => $template->department,
            'is_active' => $template->is_active,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function productsData(GlobalPlanogramTemplate $template): array
    {
        return $template->templateProducts
            ->map(fn (GlobalPlanogramTemplateProduct $p): array => [
                'id' => $p->id,
                'ean' => $p->ean,
                'product_id' => null,
                'description' => $p->description,
                'brand' => $p->brand,
                'grouping' => $p->grouping,
                'category' => $p->category,
                'subcategory' => $p->subcategory,
                'package_type' => $p->package_type,
                'package_content' => $p->package_content,
            ])
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function availableGroupings(GlobalPlanogramTemplate $template): array
    {
        return $template->subtemplates
            ->flatMap(fn (GlobalPlanogramSubtemplate $sub) => $sub->slots->pluck('grouping'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function searchProducts(string $query): array
    {
        // Global templates don't have a product catalog; return empty for now
        return [];
    }

    private function normalizeGrouping(string $value): string
    {
        return (string) preg_replace('/\s+/', ' ', strtolower(trim($value)));
    }
}
