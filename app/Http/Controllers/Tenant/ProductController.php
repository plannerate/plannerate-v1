<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\InteractsWithDeferredIndex;
use App\Http\Requests\Tenant\StoreProductRequest;
use App\Http\Requests\Tenant\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\TenantIntegration;
use App\Services\Integrations\Sysmo\SysmoSingleProductIntegrationService;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    use InteractsWithDeferredIndex;
    use InteractsWithTenantContext;

    public function syncSingle(Request $request, SysmoSingleProductIntegrationService $singleProductIntegrationService): RedirectResponse
    {
        $this->authorize('viewAny', Product::class);

        $validated = $request->validate([
            'produto' => ['required', 'string', 'max:255'],
            'empresa' => ['nullable', 'string', 'max:255'],
            'store_ids' => ['nullable', 'array'],
            'store_ids.*' => ['ulid'],
        ]);

        $tenantId = $this->tenantId();
        if ($tenantId === null || $tenantId === '') {
            abort(404);
        }

        $integration = TenantIntegration::query()
            ->where('tenant_id', $tenantId)
            ->where('integration_type', 'sysmo')
            ->where('is_active', true)
            ->first();

        if (! $integration instanceof TenantIntegration) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Integração Sysmo ativa não encontrada para este tenant.',
            ]);

            return back();
        }

        $storeIds = collect(is_array($validated['store_ids'] ?? null) ? $validated['store_ids'] : [])
            ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
            ->unique()
            ->values()
            ->all();

        $stores = $storeIds === []
            ? collect()
            : Store::query()
                ->whereIn('id', $storeIds)
                ->get(['id', 'document']);

        $documents = $stores
            ->map(fn (Store $store): ?string => $this->normalizeEmpresaValue($store->document))
            ->filter(fn (?string $document): bool => $document !== null && $document !== '')
            ->unique()
            ->values()
            ->all();

        if ($documents === [] && is_string($validated['empresa'] ?? null) && $validated['empresa'] !== '') {
            $normalizedEmpresa = $this->normalizeEmpresaValue($validated['empresa']);
            if ($normalizedEmpresa !== null) {
                $documents = [$normalizedEmpresa];
            }
        }
        if ($documents === []) {
            $documents = [''];
        }

        $foundAny = false;
        $associatedStores = 0;
        $lastMatchedProduct = null;

        try {
            foreach ($documents as $empresa) {
                $result = $singleProductIntegrationService->fetchAndPersist(
                    integration: $integration,
                    produto: (string) $validated['produto'],
                    filters: [
                        'empresa' => $empresa !== '' ? $empresa : null,
                    ],
                );

                if (! ($result['found'] ?? false)) {
                    continue;
                }

                $foundAny = true;
                $matchedProduct = $this->resolveProductFromIntegrationResult(
                    tenantId: $tenantId,
                    result: $result,
                );

                if ($matchedProduct instanceof Product) {
                    $lastMatchedProduct = $matchedProduct;
                }

                if (! $matchedProduct instanceof Product || $storeIds === []) {
                    continue;
                }

                $matchingStoreIds = $stores
                    ->filter(function (Store $store) use ($empresa): bool {
                        return $this->normalizeEmpresaValue($store->document) === $empresa;
                    })
                    ->pluck('id')
                    ->all();

                if ($matchingStoreIds === []) {
                    continue;
                }

                $pivotValues = [];
                foreach ($matchingStoreIds as $storeId) {
                    $pivotValues[(string) $storeId] = [
                        'tenant_id' => $tenantId,
                        'last_synced_at' => Carbon::now(),
                    ];
                }

                $matchedProduct->stores()->syncWithoutDetaching($pivotValues);
                $associatedStores += count($matchingStoreIds);
            }
        } catch (\Throwable $exception) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Falha ao sincronizar produto na integração: '.$exception->getMessage(),
            ]);

            return back();
        }

        if ($foundAny && $lastMatchedProduct instanceof Product) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => sprintf('Produto sincronizado com sucesso. Lojas associadas: %d.', $associatedStores),
            ]);

            return to_route('tenant.products.edit', [
                ...$this->tenantRouteParameters(),
                'product' => $lastMatchedProduct->id,
            ]);
        }

        Inertia::flash('toast', [
            'type' => $foundAny ? 'success' : 'warning',
            'message' => $foundAny
                ? sprintf('Produto sincronizado com sucesso. Lojas associadas: %d.', $associatedStores)
                : 'Produto não encontrado na integração.',
        ]);

        return back();
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $search = $this->requestString($request, 'search');
        $status = $this->requestEnum($request, 'status', ['draft', 'published', 'synced', 'error']);
        $categoryId = $this->requestString($request, 'category_id');
        $requestedSort = trim((string) $request->query('sort', ''));
        $sort = in_array($requestedSort, ['name', 'ean', 'status', 'created_at', 'category'], true) ? $requestedSort : null;
        $requestedDirection = strtolower((string) $request->query('direction', 'asc'));
        $direction = in_array($requestedDirection, ['asc', 'desc'], true) ? $requestedDirection : 'asc';

        return $this->renderDeferredIndex('tenant/products/Index', 'products', fn (): LengthAwarePaginator => $this->productsPaginator(
            $search,
            $status,
            $categoryId,
            $sort,
            $direction,
            $this->resolvePerPage($request, 10),
        ), [
            'subdomain' => $this->tenantSubdomain(),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'category_id' => $categoryId,
            ],
            'filter_options' => [
                'categories' => $this->categoriesForSelect(),
            ],
        ]);
    }

    private function productsPaginator(
        string $search,
        string $status,
        string $categoryId,
        ?string $sort,
        string $direction,
        int $perPage,
    ): LengthAwarePaginator {
        return Product::query()
            ->with(['category:id,name', 'stores:id,name'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($where) use ($search): void {
                    $where
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%')
                        ->orWhere('ean', 'like', '%'.$search.'%');
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($categoryId !== '', fn ($query) => $query->where('category_id', $categoryId))
            ->when(
                $sort !== null,
                function ($query) use ($sort, $direction): void {
                    if ($sort === 'category') {
                        $query->orderBy(
                            Category::query()
                                ->select('name')
                                ->whereColumn('categories.id', 'products.category_id')
                                ->limit(1),
                            $direction,
                        );

                        return;
                    }

                    $query->orderBy($sort, $direction);
                },
                fn ($query) => $query->latest(),
            )
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'image_url' => $product->image_url,
                'slug' => $product->slug,
                'ean' => $product->ean,
                'status' => $product->status,
                'category' => $product->category?->name,
                'stores' => $product->stores
                    ->pluck('name')
                    ->filter(fn (mixed $name): bool => is_string($name) && trim($name) !== '')
                    ->values()
                    ->all(),
                'created_at' => $product->created_at?->toDateTimeString(),
            ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Product::class);

        return Inertia::render('tenant/products/Form', [
            'subdomain' => $this->tenantSubdomain(),
            'product' => null,
            'stores' => $this->storesForSelect(),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $validated = $request->validated();
        $storeIds = collect(is_array($validated['store_ids'] ?? null) ? $validated['store_ids'] : [])
            ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
            ->unique()
            ->values()
            ->all();
        unset($validated['store_ids']);

        $productAttributes = $this->filterProductAttributesByExistingColumns([
            ...$validated,
            'user_id' => $request->user()?->getAuthIdentifier(),
            'stackable' => $request->boolean('stackable'),
            'perishable' => $request->boolean('perishable'),
            'flammable' => $request->boolean('flammable'),
            'hangable' => $request->boolean('hangable'),
            'no_sales' => $request->boolean('no_sales'),
            'no_purchases' => $request->boolean('no_purchases'),
        ]);
        $product = Product::query()->create($productAttributes);

        if ($storeIds !== []) {
            $pivotValues = [];
            foreach ($storeIds as $storeId) {
                $pivotValues[(string) $storeId] = [
                    'tenant_id' => (string) $this->tenantId(),
                    'last_synced_at' => null,
                ];
            }
            $product->stores()->sync($pivotValues);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.products.messages.created'),
        ]);

        return to_route('tenant.products.index', $this->tenantRouteParameters());
    }

    public function edit(string $subdomain, string $product): Response
    {
        unset($subdomain);
        $product = Product::query()->whereKey($product)->firstOrFail();
        $this->authorize('update', $product);

        return Inertia::render('tenant/products/Form', [
            'subdomain' => $this->tenantSubdomain(),
            'stores' => $this->storesForSelect(),
            'product' => $this->productFormPayload($product),
        ]);
    }

    public function update(UpdateProductRequest $request, string $subdomain, string $product): RedirectResponse
    {
        unset($subdomain);
        $product = Product::query()->whereKey($product)->firstOrFail();
        $this->authorize('update', $product);

        $validated = $request->validated();
        $storeIds = collect(is_array($validated['store_ids'] ?? null) ? $validated['store_ids'] : [])
            ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
            ->unique()
            ->values()
            ->all();
        unset($validated['store_ids']);

        $productAttributes = $this->filterProductAttributesByExistingColumns([
            ...$validated,
            'stackable' => $request->boolean('stackable'),
            'perishable' => $request->boolean('perishable'),
            'flammable' => $request->boolean('flammable'),
            'hangable' => $request->boolean('hangable'),
            'no_sales' => $request->boolean('no_sales'),
            'no_purchases' => $request->boolean('no_purchases'),
        ]);
        $product->update($productAttributes);

        $pivotValues = [];
        foreach ($storeIds as $storeId) {
            $pivotValues[(string) $storeId] = [
                'tenant_id' => (string) $this->tenantId(),
                'last_synced_at' => null,
            ];
        }
        $product->stores()->sync($pivotValues);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.products.messages.updated'),
        ]);

        return to_route('tenant.products.index', $this->tenantRouteParameters());
    }

    public function destroy(string $subdomain, string $product): RedirectResponse
    {
        unset($subdomain);
        $product = Product::query()->whereKey($product)->firstOrFail();
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
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string, document: string|null}>
     */
    private function storesForSelect(): array
    {
        return Store::query()
            ->orderBy('name')
            ->get(['id', 'name', 'document'])
            ->map(fn (Store $store): array => [
                'id' => (string) $store->id,
                'name' => (string) $store->name,
                'document' => is_string($store->document) && $store->document !== '' ? $store->document : null,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function resolveProductFromIntegrationResult(string $tenantId, array $result): ?Product
    {
        $mappedItem = is_array($result['mapped_item'] ?? null) ? $result['mapped_item'] : [];
        $ean = is_string($mappedItem['ean'] ?? null) ? trim($mappedItem['ean']) : null;
        $codigoErp = is_string($mappedItem['external_id'] ?? null) ? trim($mappedItem['external_id']) : null;

        $query = Product::query()->where('tenant_id', $tenantId);

        if ($ean !== null && $ean !== '') {
            $query->where('ean', $ean);

            return $query->first();
        }

        if ($codigoErp !== null && $codigoErp !== '') {
            $query->where('codigo_erp', $codigoErp);

            return $query->first();
        }

        return null;
    }

    private function normalizeEmpresaValue(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        return $digits !== '' ? $digits : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function productFormPayload(Product $product): array
    {
        return [
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
            'image_url' => $product->image_url,
            'store_ids' => $product->stores()->pluck('stores.id')->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function filterProductAttributesByExistingColumns(array $attributes): array
    {
        static $productColumns = null;

        if (! is_array($productColumns)) {
            $connectionName = (string) (config('multitenancy.tenant_database_connection_name') ?: config('database.default'));
            $productColumns = Schema::connection($connectionName)->getColumnListing('products');
        }

        $allowedColumns = array_flip($productColumns);

        return array_intersect_key($attributes, $allowedColumns);
    }
}
