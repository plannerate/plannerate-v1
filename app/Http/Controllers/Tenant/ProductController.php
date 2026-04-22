<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreProductRequest;
use App\Http\Requests\Tenant\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    use InteractsWithTenantContext;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $tenantId = $this->tenantId();
        $search = trim((string) $request->string('search'));
        $status = trim((string) $request->string('status'));
        $categoryId = trim((string) $request->string('category_id'));
        $hasStatusFilter = in_array($status, ['draft', 'published', 'synced', 'error'], true);
        $hasCategoryFilter = $categoryId !== '';

        $products = Product::query()
            ->where('tenant_id', $tenantId)
            ->with(['category:id,name'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($where) use ($search): void {
                    $where
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%')
                        ->orWhere('ean', 'like', '%'.$search.'%');
                });
            })
            ->when($hasStatusFilter, fn ($query) => $query->where('status', $status))
            ->when($hasCategoryFilter, fn ($query) => $query->where('category_id', $categoryId))
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'ean' => $product->ean,
                'status' => $product->status,
                'category' => $product->category?->name,
                'created_at' => $product->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('tenant/products/Index', [
            'subdomain' => $this->tenantSubdomain(),
            'products' => $products,
            'filters' => [
                'search' => $search,
                'status' => $hasStatusFilter ? $status : '',
                'category_id' => $hasCategoryFilter ? $categoryId : '',
            ],
            'filter_options' => [
                'categories' => $this->categoriesForSelect(),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Product::class);

        return Inertia::render('tenant/products/Form', [
            'subdomain' => $this->tenantSubdomain(),
            'product' => null,
            'categories' => $this->categoriesForSelect(),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $validated = $request->validated();

        Product::query()->create([
            ...$validated,
            'tenant_id' => $this->tenantId(),
            'user_id' => $request->user()?->getAuthIdentifier(),
            'stackable' => $request->boolean('stackable'),
            'perishable' => $request->boolean('perishable'),
            'flammable' => $request->boolean('flammable'),
            'hangable' => $request->boolean('hangable'),
            'no_sales' => $request->boolean('no_sales'),
            'no_purchases' => $request->boolean('no_purchases'),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.products.messages.created'),
        ]);

        return to_route('tenant.products.index', $this->tenantRouteParameters());
    }

    public function edit(string $subdomain, Product $product): Response
    {
        unset($subdomain);
        $this->ensureTenantOwnership($product);
        $this->authorize('update', $product);

        return Inertia::render('tenant/products/Form', [
            'subdomain' => $this->tenantSubdomain(),
            'product' => [
                'id' => $product->id,
                'category_id' => $product->category_id,
                'name' => $product->name,
                'slug' => $product->slug,
                'ean' => $product->ean,
                'codigo_erp' => $product->codigo_erp,
                'stackable' => (bool) $product->stackable,
                'perishable' => (bool) $product->perishable,
                'flammable' => (bool) $product->flammable,
                'hangable' => (bool) $product->hangable,
                'description' => $product->description,
                'sales_status' => $product->sales_status,
                'sales_purchases' => $product->sales_purchases,
                'status' => $product->status,
                'sync_source' => $product->sync_source,
                'sync_at' => $product->sync_at?->format('Y-m-d\TH:i'),
                'no_sales' => (bool) $product->no_sales,
                'no_purchases' => (bool) $product->no_purchases,
                'url' => $product->url,
                'type' => $product->type,
                'reference' => $product->reference,
                'fragrance' => $product->fragrance,
                'flavor' => $product->flavor,
                'color' => $product->color,
                'brand' => $product->brand,
                'subbrand' => $product->subbrand,
                'packaging_type' => $product->packaging_type,
                'packaging_size' => $product->packaging_size,
                'measurement_unit' => $product->measurement_unit,
                'packaging_content' => $product->packaging_content,
                'unit_measure' => $product->unit_measure,
                'auxiliary_description' => $product->auxiliary_description,
                'additional_information' => $product->additional_information,
                'sortiment_attribute' => $product->sortiment_attribute,
                'dimensions_ean' => $product->dimensions_ean,
                'width' => $product->width,
                'height' => $product->height,
                'depth' => $product->depth,
                'weight' => $product->weight,
                'unit' => $product->unit,
                'dimensions_status' => $product->dimensions_status,
                'dimensions_description' => $product->dimensions_description,
            ],
            'categories' => $this->categoriesForSelect(),
        ]);
    }

    public function update(UpdateProductRequest $request, string $subdomain, Product $product): RedirectResponse
    {
        unset($subdomain);
        $this->ensureTenantOwnership($product);
        $this->authorize('update', $product);

        $validated = $request->validated();

        $product->update([
            ...$validated,
            'stackable' => $request->boolean('stackable'),
            'perishable' => $request->boolean('perishable'),
            'flammable' => $request->boolean('flammable'),
            'hangable' => $request->boolean('hangable'),
            'no_sales' => $request->boolean('no_sales'),
            'no_purchases' => $request->boolean('no_purchases'),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.products.messages.updated'),
        ]);

        return to_route('tenant.products.index', $this->tenantRouteParameters());
    }

    public function destroy(string $subdomain, Product $product): RedirectResponse
    {
        unset($subdomain);
        $this->ensureTenantOwnership($product);
        $this->authorize('delete', $product);

        $product->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.products.messages.deleted'),
        ]);

        return to_route('tenant.products.index', $this->tenantRouteParameters());
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function categoriesForSelect(): array
    {
        return Category::query()
            ->where('tenant_id', $this->tenantId())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
            ])
            ->all();
    }

    private function ensureTenantOwnership(Product $product): void
    {
        $this->ensureBelongsToCurrentTenant($product);
    }
}
