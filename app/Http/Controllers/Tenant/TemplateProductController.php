<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\InteractsWithDeferredIndex;
use App\Models\PlanogramSubtemplate;
use App\Models\PlanogramTemplate;
use App\Models\PlanogramTemplateProduct;
use App\Models\Product;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TemplateProductController extends Controller
{
    use InteractsWithDeferredIndex;
    use InteractsWithTenantContext;

    public function index(Request $request, string $subdomain, PlanogramTemplate $planogramTemplate): Response
    {
        unset($subdomain);
        $this->authorize('view', $planogramTemplate);

        $planogramTemplate->load(['subtemplates.slots', 'templateProducts']);

        $searchQuery = $this->requestString($request, 'search');

        return Inertia::render('tenant/planogram-templates/Products', [
            'subdomain' => $this->tenantSubdomain(),
            'template' => $this->templateData($planogramTemplate),
            'products' => $this->productsData($planogramTemplate),
            'availableGroupings' => $this->availableGroupings($planogramTemplate),
            'searchResults' => $searchQuery !== '' ? $this->searchProducts($searchQuery) : null,
        ]);
    }

    public function store(Request $request, string $subdomain, PlanogramTemplate $planogramTemplate): Response
    {
        unset($subdomain);
        $this->authorize('update', $planogramTemplate);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.ean' => ['required', 'string', 'max:50'],
            'items.*.grouping' => ['required', 'string', 'max:255'],
        ]);

        foreach ($validated['items'] as $item) {
            $ean = $item['ean'];
            $grouping = $item['grouping'];

            $alreadyExists = $planogramTemplate->templateProducts()
                ->where('ean', $ean)
                ->where('grouping_normalized', $this->normalizeGrouping($grouping))
                ->exists();

            if ($alreadyExists) {
                continue;
            }

            $product = Product::where('ean', $ean)->first();

            $planogramTemplate->templateProducts()->create([
                'tenant_id' => $this->tenantId(),
                'ean' => $ean,
                'product_id' => $product?->id,
                'description' => $product?->name ?? '',
                'department' => $planogramTemplate->department,
                'brand' => $product?->brand ?? '',
                'grouping' => $grouping,
                'grouping_normalized' => $this->normalizeGrouping($grouping),
            ]);
        }

        $planogramTemplate->load('templateProducts');

        return Inertia::render('tenant/planogram-templates/Products', [
            'products' => $this->productsData($planogramTemplate),
        ]);
    }

    public function update(Request $request, string $subdomain, PlanogramTemplate $planogramTemplate, PlanogramTemplateProduct $planogramTemplateProduct): Response
    {
        unset($subdomain);
        $this->authorize('update', $planogramTemplate);

        $validated = $request->validate([
            'grouping' => ['required', 'string', 'max:255'],
        ]);

        $planogramTemplateProduct->update([
            'grouping' => $validated['grouping'],
            'grouping_normalized' => $this->normalizeGrouping($validated['grouping']),
        ]);

        $planogramTemplate->load('templateProducts');

        return Inertia::render('tenant/planogram-templates/Products', [
            'products' => $this->productsData($planogramTemplate),
        ]);
    }

    public function destroy(string $subdomain, PlanogramTemplate $planogramTemplate, PlanogramTemplateProduct $planogramTemplateProduct): Response
    {
        unset($subdomain);
        $this->authorize('update', $planogramTemplate);

        $planogramTemplateProduct->delete();

        $planogramTemplate->load('templateProducts');

        return Inertia::render('tenant/planogram-templates/Products', [
            'products' => $this->productsData($planogramTemplate),
        ]);
    }

    public function bulkImport(Request $request, string $subdomain, PlanogramTemplate $planogramTemplate): Response
    {
        unset($subdomain);
        $this->authorize('update', $planogramTemplate);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        // TODO: implement bulk import via TemplateImportService (Prompt 17)

        $planogramTemplate->load('templateProducts');

        return Inertia::render('tenant/planogram-templates/Products', [
            'products' => $this->productsData($planogramTemplate),
        ]);
    }

    public function downloadTemplate(string $subdomain, PlanogramTemplate $planogramTemplate): never
    {
        unset($subdomain);
        $this->authorize('view', $planogramTemplate);

        // TODO: generate Excel template (Prompt 17)
        abort(501, 'Download de modelo ainda não implementado.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function templateData(PlanogramTemplate $planogramTemplate): array
    {
        return [
            'id' => $planogramTemplate->id,
            'code' => $planogramTemplate->code,
            'name' => $planogramTemplate->name,
            'department' => $planogramTemplate->department,
            'is_active' => $planogramTemplate->is_active,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function productsData(PlanogramTemplate $planogramTemplate): array
    {
        return $planogramTemplate->templateProducts
            ->map(fn (PlanogramTemplateProduct $p): array => [
                'id' => $p->id,
                'ean' => $p->ean,
                'product_id' => $p->product_id,
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
    private function availableGroupings(PlanogramTemplate $planogramTemplate): array
    {
        return $planogramTemplate->subtemplates
            ->flatMap(fn (PlanogramSubtemplate $sub) => $sub->slots->pluck('grouping'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function searchProducts(string $query): array
    {
        return Product::where(function ($q) use ($query): void {
            $q->where('ean', 'like', '%'.$query.'%')
                ->orWhere('name', 'like', '%'.$query.'%')
                ->orWhere('brand', 'like', '%'.$query.'%');
        })
            ->limit(30)
            ->get()
            ->map(fn (Product $p): array => [
                'id' => $p->id,
                'ean' => (string) $p->ean,
                'name' => (string) $p->name,
                'brand' => (string) ($p->brand ?? ''),
            ])
            ->all();
    }

    private function normalizeGrouping(string $value): string
    {
        return (string) preg_replace('/\s+/', ' ', strtolower(trim($value)));
    }
}
