<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Concerns\InteractsWithDeferredIndex;
use App\Http\Controllers\Controller;
use App\Models\GlobalPlanogramTemplate;
use App\Models\GlobalPlanogramTemplateProduct;
use App\Services\AutoPlanogram\Template\TemplateProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GlobalTemplateProductController extends Controller
{
    use InteractsWithDeferredIndex;

    public function __construct(private readonly TemplateProductService $service) {}

    public function index(Request $request, GlobalPlanogramTemplate $globalPlanogramTemplate): Response
    {
        $this->authorize('view', $globalPlanogramTemplate);

        $globalPlanogramTemplate->load(['subtemplates.slots', 'templateProducts']);

        $searchQuery = $this->requestString($request, 'search');

        return Inertia::render('landlord/planogram-templates/Products', [
            'template' => $this->service->templateData($globalPlanogramTemplate),
            'products' => $this->service->productsData($globalPlanogramTemplate),
            'availableGroupings' => $this->service->availableGroupings($globalPlanogramTemplate),
            'searchResults' => $searchQuery !== '' ? [] : null,
        ]);
    }

    public function store(Request $request, GlobalPlanogramTemplate $globalPlanogramTemplate): RedirectResponse
    {
        $this->authorize('update', $globalPlanogramTemplate);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.ean' => ['required', 'string', 'max:50'],
            'items.*.grouping' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.brand' => ['nullable', 'string', 'max:255'],
        ]);

        $this->service->storeProducts(
            $globalPlanogramTemplate,
            $validated['items'],
            fn(string $ean, array $item): array => [
                'description' => $item['description'] ?? '',
                'department' => $globalPlanogramTemplate->department,
                'brand' => $item['brand'] ?? '',
            ],
        );

        $globalPlanogramTemplate->load('templateProducts');

        return  redirect()->route('landlord.planogram-templates.products.index', [
            'globalPlanogramTemplate' => $globalPlanogramTemplate->id,
        ]);
    }

    public function update(Request $request, GlobalPlanogramTemplate $globalPlanogramTemplate, GlobalPlanogramTemplateProduct $globalPlanogramTemplateProduct): RedirectResponse
    {
        $this->authorize('update', $globalPlanogramTemplate);

        $validated = $request->validate([
            'grouping' => ['required', 'string', 'max:255'],
        ]);

        $this->service->updateProduct($globalPlanogramTemplateProduct, $validated['grouping']);

        $globalPlanogramTemplate->load('templateProducts');

        return  redirect()->route('landlord.planogram-templates.products.index', [
            'globalPlanogramTemplate' => $globalPlanogramTemplate->id,
        ]);
    }

    public function destroy(GlobalPlanogramTemplate $globalPlanogramTemplate, GlobalPlanogramTemplateProduct $globalPlanogramTemplateProduct): RedirectResponse
    {
        $this->authorize('update', $globalPlanogramTemplate);

        $this->service->destroyProduct($globalPlanogramTemplateProduct);

        $globalPlanogramTemplate->load('templateProducts');

        return  redirect()->route('landlord.planogram-templates.products.index', [
            'globalPlanogramTemplate' => $globalPlanogramTemplate->id,
        ]);
    }

    public function bulkImport(Request $request, GlobalPlanogramTemplate $globalPlanogramTemplate): RedirectResponse
    {
        $this->authorize('update', $globalPlanogramTemplate);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        // TODO: implement bulk import (Prompt 17)

        $globalPlanogramTemplate->load('templateProducts');

        return  redirect()->route('landlord.planogram-templates.products.index', [
            'globalPlanogramTemplate' => $globalPlanogramTemplate->id,
        ]);
    }

    public function downloadTemplate(GlobalPlanogramTemplate $globalPlanogramTemplate): never
    {
        $this->authorize('view', $globalPlanogramTemplate);

        // TODO: generate Excel template (Prompt 17)
        abort(501, 'Download de modelo ainda não implementado.');
    }
}
