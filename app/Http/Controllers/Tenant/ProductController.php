<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\InteractsWithCategoryFilter;
use App\Http\Controllers\Concerns\InteractsWithPlanLimits;
use App\Http\Controllers\Concerns\InteractsWithSyncImageDownLoad;
use App\Http\Controllers\Concerns\InteractsWithTrashedFilter;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\InteractsWithDeferredIndex;
use App\Http\Requests\Tenant\StoreProductRequest;
use App\Http\Requests\Tenant\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Support\Tenancy\InteractsWithTenantContext;
use Callcocam\LaravelIntegrations\Jobs\SyncSingleProductJob;
use Callcocam\LaravelIntegrations\Models\TenantIntegration;
use Callcocam\LaravelRaptorPlannerate\Models\Sale;
use Callcocam\LaravelRaptorPlannerate\Sales\SalesFilters;
use Callcocam\LaravelRaptorPlannerate\Sales\SalesQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    use InteractsWithCategoryFilter;
    use InteractsWithDeferredIndex;
    use InteractsWithPlanLimits;
    use InteractsWithSyncImageDownLoad;
    use InteractsWithTenantContext;
    use InteractsWithTrashedFilter;

    public function syncSingle(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', Product::class);

        $validated = $request->validate([
            'product' => ['required', 'ulid'],
            'store_id' => ['required', 'ulid'],
            'update_product' => ['sometimes', 'boolean'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        // Garante que produto e loja pertencem ao tenant atual (conexão tenant).
        $product = Product::query()->findOrFail($validated['product']);
        $store = Store::query()->findOrFail($validated['store_id']);

        $integration = TenantIntegration::query()
            ->where('tenant_id', $this->tenantId())
            ->where('is_active', true)
            ->first();

        if ($integration === null) {
            Log::warning('syncSingle: nenhuma integração ativa', [
                'tenant_id' => $this->tenantId(),
                'tenant_current' => optional(Tenant::current())->getKey(),
                'integrations_for_tenant' => TenantIntegration::query()->where('tenant_id', $this->tenantId())->count(),
                'active_for_tenant' => TenantIntegration::query()->where('tenant_id', $this->tenantId())->where('is_active', true)->count(),
            ]);

            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => __('app.tenant.products.sync.no_integration'),
            ]);

            return back();
        }

        SyncSingleProductJob::dispatch(
            (string) $this->tenantId(),
            (string) $product->getKey(),
            (string) $store->getKey(),
            $request->boolean('update_product'),
            (string) $request->user()?->getKey(),
            $validated['date_from'] ?? null,
            $validated['date_to'] ?? null,
        );

        Inertia::flash('toast', [
            'type' => 'info',
            'message' => __('app.tenant.products.sync.started'),
        ]);

        return back();
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $search = $this->requestString($request, 'search');
        $status = $this->requestEnum($request, 'status', ['draft', 'published', 'synced', 'error']);
        $categoryId = $this->requestString($request, 'category_id');
        $grouping = $this->requestString($request, 'grouping');
        $trashed = $this->resolveTrashedFilter($request);
        $requestedSort = trim((string) $request->query('sort', ''));
        $sort = in_array($requestedSort, ['name', 'ean', 'status', 'created_at', 'category'], true) ? $requestedSort : null;
        $requestedDirection = strtolower((string) $request->query('direction', 'asc'));
        $direction = in_array($requestedDirection, ['asc', 'desc'], true) ? $requestedDirection : 'asc';

        return $this->renderDeferredIndex('tenant/products/Index', 'products', fn (): LengthAwarePaginator => $this->productsPaginator(
            $search,
            $status,
            $categoryId,
            $grouping,
            $trashed,
            $sort,
            $direction,
            $this->resolvePerPage($request, 10),
        ), [
            'filters' => [
                'search' => $search,
                'status' => $status,
                'category_id' => $categoryId,
                'grouping' => $grouping,
                'trashed' => $trashed,
            ],
            'filter_options' => [
                'categories' => $this->categoriesForSelect(),
                'groupings' => $this->groupingsForSelect(),
            ],
            'can' => $this->resolveCanCreate(Product::class, 'product_limit', Product::count()),
        ]);
    }

    public function sortimentAttributes(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));

        $attributes = Product::query()
            ->select('sortiment_attribute')
            ->whereNotNull('sortiment_attribute')
            ->where('sortiment_attribute', '!=', '')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('sortiment_attribute', 'like', '%'.$search.'%');
            })
            ->distinct()
            ->orderBy('sortiment_attribute')
            ->limit(20)
            ->pluck('sortiment_attribute')
            ->filter(fn (mixed $attribute): bool => is_string($attribute) && trim($attribute) !== '')
            ->values();

        return response()->json([
            'data' => $attributes,
        ]);
    }

    private function productsPaginator(
        string $search,
        string $status,
        string $categoryId,
        string $grouping,
        string $trashed,
        ?string $sort,
        string $direction,
        int $perPage,
    ): LengthAwarePaginator {
        $query = Product::query();
        $categoryIds = $this->categoryAndDescendantIds($categoryId);

        $this->applyTrashedToQuery($query, $trashed);

        return $query
            // Estoque e última compra vivem em `product_store` (métrica POR LOJA).
            // A listagem é tenant-wide, então mostra o consolidado: soma do estoque
            // de todas as lojas e a compra mais recente entre elas.
            ->withStoreMetrics()
            ->with(['category:id,name', 'stores:id,name'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($where) use ($search): void {
                    $where
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%')
                        ->orWhere('sortiment_attribute', 'like', '%'.$search.'%')
                        ->orWhere('ean', 'like', '%'.$search.'%');
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($categoryIds !== [], fn ($query) => $query->whereIn('category_id', $categoryIds))
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
                'codigo_erp' => $product->codigo_erp,
                'status' => $product->status,
                'category' => $product->category?->name,
                'stores' => $product->stores
                    ->pluck('name')
                    ->filter(fn (mixed $name): bool => is_string($name) && trim($name) !== '')
                    ->values()
                    ->all(),
                'created_at' => $product->created_at?->toDateTimeString(),
                'sync_at' => $product->sync_at?->toDateTimeString(),
                'trashed' => $product->trashed(),
                'dimensions' => [
                    'width' => $product->width,
                    'height' => $product->height,
                    'depth' => $product->depth,
                    'weight' => $product->weight,
                    'unit' => $product->unit,
                ],
                'current_stock' => $product->current_stock,
                'last_purchase_date' => $product->last_purchase_date?->toDateTimeString(),
            ]);
    }

    /**
     * Exibe o mini-dashboard de vendas de um produto específico com filtros
     * por período e promoção, além de totalizadores agregados.
     */
    public function sales(Request $request, string $product): Response
    {
        $product = Product::query()->findOrFail($product);
        $this->authorize('view', $product);

        $filters = SalesFilters::fromProductSalesRequest($request);
        $requestedSort = trim((string) $request->query('sort', ''));
        $sort = in_array($requestedSort, ['sale_date', 'total_sale_quantity', 'total_sale_value', 'store'], true)
            ? $requestedSort
            : null;
        $requestedDirection = strtolower((string) $request->query('direction', 'desc'));
        $direction = in_array($requestedDirection, ['asc', 'desc'], true) ? $requestedDirection : 'desc';

        // Casamento produto↔venda + filtros centralizados no SalesQuery: a mesma
        // base alimenta os totalizadores e a tabela paginada.
        $salesQuery = SalesQuery::make()
            ->forProduct($product)
            ->applyFilters($filters);

        /** @var Builder<Sale> $baseQuery */
        $baseQuery = $salesQuery->builder();

        // ── Totalizadores e métricas derivadas (fonte única de verdade) ──
        $totals = $salesQuery->summary()->toArray();

        // ── Tabela paginada ───────────────────────────────────────────────
        $perPage = $this->resolvePerPage($request, 15);
        $sales = (clone $baseQuery)
            ->with(['store:id,name'])
            ->when(
                $sort !== null,
                function ($q) use ($sort, $direction): void {
                    if ($sort === 'store') {
                        $q->orderBy(
                            Store::query()->select('name')->whereColumn('stores.id', 'sales.store_id')->limit(1),
                            $direction,
                        );

                        return;
                    }
                    $q->orderBy($sort, $direction);
                },
                fn ($q) => $q->latest('sale_date'),
            )
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Sale $sale): array => [
                'id' => $sale->id,
                'store' => $sale->store?->name,
                'sale_date' => $sale->sale_date?->toDateString(),
                'promotion' => $sale->promotion,
                'total_sale_quantity' => $sale->total_sale_quantity,
                'total_sale_value' => $sale->total_sale_value,
                'acquisition_cost' => $sale->acquisition_cost,
                'sale_price' => $sale->sale_price,
                'total_profit_margin' => $sale->total_profit_margin,
                'margem_contribuicao' => $sale->margem_contribuicao,
                'extra_data' => $sale->extra_data,
            ]);

        return Inertia::render('tenant/products/Sales', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'ean' => $product->ean,
                'codigo_erp' => $product->codigo_erp,
                'image_url' => $product->image_url ?? null,
            ],
            'sales' => $sales,
            'totals' => $totals,
            'filters' => [
                'sale_date_from' => $filters->saleDateFrom ?? '',
                'sale_date_to' => $filters->saleDateTo ?? '',
                'promotion' => $filters->promotion ?? '',
                'store_id' => $filters->storeId ?? '',
            ],
            'filter_options' => [
                'stores' => $this->storesForSelect(),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Product::class);

        return Inertia::render('tenant/products/Form', [
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

        return $this->toTenantRoute('tenant.products.index');
    }

    public function edit(string $product): Response
    {
        $product = Product::query()->whereKey($product)->firstOrFail();
        $this->authorize('update', $product);

        return Inertia::render('tenant/products/Form', [
            'stores' => $this->storesForSelect(),
            'product' => $this->productFormPayload($product),
        ]);
    }

    public function update(UpdateProductRequest $request, string $product): RedirectResponse
    {
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

        return $this->toTenantRoute('tenant.products.index');
    }

    public function destroy(string $product): RedirectResponse
    {
        $product = Product::query()->withTrashed()->whereKey($product)->firstOrFail();
        $this->authorize('delete', $product);

        if ($product->trashed()) {
            $product->forceDelete();

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('app.tenant.products.messages.force_deleted'),
            ]);

            return $this->toTenantRoute('tenant.products.index');
        }

        $product->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.products.messages.deleted'),
        ]);

        return $this->toTenantRoute('tenant.products.index');
    }

    public function restore(string $product): RedirectResponse
    {
        $product = Product::query()->onlyTrashed()->whereKey($product)->firstOrFail();
        $this->authorize('delete', $product);

        $product->restore();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.products.messages.restored'),
        ]);

        return $this->toTenantRoute('tenant.products.index');
    }

    /**
     * @return list<string>
     */
    private function groupingsForSelect(): array
    {
        return [];
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
            'last_purchase_date' => $product->last_purchase_date?->format('Y-m-d'),
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
            'grouping' => $product->grouping,
            'sortiment_attribute' => $product->sortiment_attribute,
            'sortiment_attribute_levels' => $product->sortiment_attribute_levels,
            'dimensions_ean' => $product->dimensions_ean,
            'width' => $product->width,
            'height' => $product->height,
            'depth' => $product->depth,
            'weight' => $product->weight,
            'unit' => $product->unit,
            'dimensions_status' => $product->dimension_publish_status,
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
