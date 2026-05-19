<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\InteractsWithDeferredIndex;
use App\Models\PlanogramTemplate;
use App\Models\PlanogramTemplateProduct;
use App\Models\Product;
use App\Services\AutoPlanogram\Template\TemplateProductService;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TemplateProductController extends Controller
{
    use InteractsWithDeferredIndex;
    use InteractsWithTenantContext;

    public function __construct(private readonly TemplateProductService $service) {}

    public function index(Request $request, string $subdomain, PlanogramTemplate $planogramTemplate): Response
    {
        unset($subdomain);
        $this->authorize('view', $planogramTemplate);

        $planogramTemplate->load(['subtemplates.slots', 'templateProducts.product']);

        $selectedGroupingId = $this->requestString($request, 'groupingId');
        $selectedGroupingName = $this->service->resolveGroupingNameById($planogramTemplate, $selectedGroupingId);

        return Inertia::render('tenant/planogram-templates/Products', [
            'subdomain' => $this->tenantSubdomain(),
            'template' => $this->service->templateData($planogramTemplate),
            'products' => $this->service->productsData($planogramTemplate),
            'availableGroupings' => $this->service->availableGroupings($planogramTemplate),
            'groupingOptions' => $this->service->groupingOptions($planogramTemplate),
            'selectedGroupingId' => $selectedGroupingName !== null ? $selectedGroupingId : null,
            'searchResults' => $selectedGroupingName !== null ? $this->searchProductsByGrouping($selectedGroupingName) : null,
        ]);
    }

    public function store(Request $request, string $subdomain, PlanogramTemplate $planogramTemplate): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('update', $planogramTemplate);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.ean' => ['required', 'string', 'max:50'],
            'items.*.grouping' => ['required', 'string', 'max:255'],
            'items.*.sortiment_attribute' => ['nullable', 'string', 'max:255'],
        ]);

        $this->service->storeProducts(
            $planogramTemplate,
            $validated['items'],
            function (string $ean, array $item) use ($planogramTemplate): array {
                $product = Product::where('ean', $ean)->first();
                $sortimentAttribute = (string) ($product?->sortiment_attribute ?? $item['sortiment_attribute'] ?? '');
                [$category, $subcategory] = $this->extractCategoryAndSubcategory(
                    $sortimentAttribute,
                    (string) $planogramTemplate->department,
                );

                return [
                    'product_id' => $product?->id,
                    'description' => $product?->name ?? '',
                    'department' => $planogramTemplate->department,
                    'category' => $category,
                    'subcategory' => $subcategory,
                    'brand' => $product?->brand ?? '',
                ];
            },
            ['tenant_id' => $this->tenantId()],
        );

        $planogramTemplate->load('templateProducts');

        return redirect()->route('tenant.planogram-templates.products.index', [
            'subdomain' => $this->tenantSubdomain(),
            'planogramTemplate' => $planogramTemplate->id,
        ]);
    }

    public function update(Request $request, string $subdomain, PlanogramTemplate $planogramTemplate, PlanogramTemplateProduct $planogramTemplateProduct): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('update', $planogramTemplate);

        $validated = $request->validate([
            'grouping' => ['required', 'string', 'max:255'],
        ]);

        $this->service->updateProduct($planogramTemplateProduct, $validated['grouping']);

        $planogramTemplate->load('templateProducts');

        return redirect()->route('tenant.planogram-templates.products.index', [
            'subdomain' => $this->tenantSubdomain(),
            'planogramTemplate' => $planogramTemplate->id,
        ]);
    }

    public function destroy(string $subdomain, PlanogramTemplate $planogramTemplate, PlanogramTemplateProduct $planogramTemplateProduct): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('update', $planogramTemplate);

        $this->service->destroyProduct($planogramTemplateProduct);

        $planogramTemplate->load('templateProducts');

        return redirect()->route('tenant.planogram-templates.products.index', [
            'subdomain' => $this->tenantSubdomain(),
            'planogramTemplate' => $planogramTemplate->id,
        ]);
    }

    public function bulkImport(Request $request, string $subdomain, PlanogramTemplate $planogramTemplate): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('update', $planogramTemplate);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        // TODO: implement bulk import via TemplateImportService (Prompt 17)

        $planogramTemplate->load('templateProducts');

        return redirect()->route('tenant.planogram-templates.products.index', [
            'subdomain' => $this->tenantSubdomain(),
            'planogramTemplate' => $planogramTemplate->id,
        ]);
    }

    public function downloadTemplate(string $subdomain, PlanogramTemplate $planogramTemplate): never
    {
        unset($subdomain);
        $this->authorize('view', $planogramTemplate);

        // TODO: generate Excel template (Prompt 17)
        abort(501, 'Download de modelo ainda não implementado.');
    }

    /** @return list<array<string, mixed>> */
    private function searchProductsByGrouping(string $grouping): array
    {
        return Product::query()
            ->where('sortiment_attribute', $grouping)
            ->limit(200)
            ->get()
            ->map(fn (Product $p): array => [
                'id' => $p->id,
                'ean' => (string) $p->ean,
                'name' => (string) $p->name,
                'brand' => (string) ($p->brand ?? ''),
                'sortiment_attribute' => $p->sortiment_attribute,
            ])
            ->all();
    }

    /** @return array{0:string,1:string} */
    private function extractCategoryAndSubcategory(string $sortimentAttribute, string $fallback): array
    {
        $segments = array_values(array_filter(
            array_map('trim', preg_split('/\s*\|\s*/', $sortimentAttribute) ?: []),
            fn (string $segment): bool => $segment !== '',
        ));

        if ($segments === []) {
            return [$fallback, $fallback];
        }

        if (count($segments) === 1) {
            return [$segments[0], $segments[0]];
        }

        return [$segments[count($segments) - 2], $segments[count($segments) - 1]];
    }
}
