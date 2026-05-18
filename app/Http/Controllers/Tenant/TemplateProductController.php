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

        $planogramTemplate->load(['subtemplates.slots', 'templateProducts']);

        $searchQuery = $this->requestString($request, 'search');

        return Inertia::render('tenant/planogram-templates/Products', [
            'subdomain' => $this->tenantSubdomain(),
            'template' => $this->service->templateData($planogramTemplate),
            'products' => $this->service->productsData($planogramTemplate),
            'availableGroupings' => $this->service->availableGroupings($planogramTemplate),
            'searchResults' => $searchQuery !== '' ? $this->searchProducts($searchQuery) : null,
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
        ]);

        $this->service->storeProducts(
            $planogramTemplate,
            $validated['items'],
            function (string $ean) use ($planogramTemplate): array {
                $product = Product::where('ean', $ean)->first();

                return [
                    'product_id' => $product?->id,
                    'description' => $product?->name ?? '',
                    'department' => $planogramTemplate->department,
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
    private function searchProducts(string $query): array
    {
        return Product::where(function ($q) use ($query): void {
            $q->where('ean', 'like', '%' . $query . '%')
                ->orWhere('name', 'like', '%' . $query . '%')
                ->orWhere('brand', 'like', '%' . $query . '%');
        })
            ->limit(30)
            ->get()
            ->map(fn(Product $p): array => [
                'id' => $p->id,
                'ean' => (string) $p->ean,
                'name' => (string) $p->name,
                'brand' => (string) ($p->brand ?? ''),
            ])
            ->all();
    }
}
