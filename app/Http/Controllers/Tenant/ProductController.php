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
use App\Models\Sale;
use App\Models\Store;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $request->validate([
            'produto' => ['required', 'string', 'max:255'],
            'empresa' => ['nullable', 'string', 'max:255'],
            'store_ids' => ['nullable', 'array'],
            'store_ids.*' => ['ulid'],
        ]);

        Inertia::flash('toast', [
            'type' => 'info',
            'message' => 'Busca de produto mockada enquanto o novo sistema de importação é construído.',
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

        $saleDateFrom = $this->requestString($request, 'sale_date_from');
        $saleDateTo   = $this->requestString($request, 'sale_date_to');
        $promotion    = $this->requestString($request, 'promotion');
        $storeId      = $this->requestString($request, 'store_id');
        $requestedSort = trim((string) $request->query('sort', ''));
        $sort = in_array($requestedSort, ['sale_date', 'total_sale_quantity', 'total_sale_value', 'store'], true)
            ? $requestedSort
            : null;
        $requestedDirection = strtolower((string) $request->query('direction', 'desc'));
        $direction = in_array($requestedDirection, ['asc', 'desc'], true) ? $requestedDirection : 'desc';

        /** @var \Illuminate\Database\Eloquent\Builder<Sale> $baseQuery */
        $baseQuery = Sale::query()
            ->where(function ($q) use ($product): void {
                $q->where('product_id', $product->id)
                    ->orWhere('ean', $product->ean)
                    ->orWhere('codigo_erp', $product->codigo_erp);
            })
            ->when($saleDateFrom !== '', fn ($q) => $q->whereDate('sale_date', '>=', $saleDateFrom))
            ->when($saleDateTo !== '', fn ($q) => $q->whereDate('sale_date', '<=', $saleDateTo))
            ->when($promotion !== '', fn ($q) => $q->where('promotion', $promotion))
            ->when($storeId !== '', fn ($q) => $q->where('store_id', $storeId));

        // ── Totalizadores para o mini-dashboard ──────────────────────────
        $totals = (clone $baseQuery)->selectRaw(
            'COUNT(*) as total_records,
            SUM(total_sale_quantity) as total_quantity,
            SUM(total_sale_value) as total_value,
            SUM(acquisition_cost) as total_acquisition_cost,
            SUM(total_profit_margin) as total_profit_margin,
            SUM(margem_contribuicao) as total_margem_contribuicao,
            AVG(sale_price) as avg_sale_price,
            SUM(CASE WHEN promotion = \'S\' THEN 1 ELSE 0 END) as promo_records,
            SUM(CASE WHEN promotion = \'S\' THEN total_sale_quantity ELSE 0 END) as promo_quantity,
            SUM(CASE WHEN promotion = \'S\' THEN total_sale_value ELSE 0 END) as promo_value,
            SUM(CASE WHEN promotion != \'S\' OR promotion IS NULL THEN total_sale_quantity ELSE 0 END) as regular_quantity,
            SUM(CASE WHEN promotion != \'S\' OR promotion IS NULL THEN total_sale_value ELSE 0 END) as regular_value'
        )->first();

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
                'id'                   => $sale->id,
                'store'                => $sale->store?->name,
                'sale_date'            => $sale->sale_date?->toDateString(),
                'promotion'            => $sale->promotion,
                'total_sale_quantity'  => $sale->total_sale_quantity,
                'total_sale_value'     => $sale->total_sale_value,
                'acquisition_cost'     => $sale->acquisition_cost,
                'sale_price'           => $sale->sale_price,
                'total_profit_margin'  => $sale->total_profit_margin,
                'margem_contribuicao'  => $sale->margem_contribuicao,
                'extra_data'           => $sale->extra_data,
            ]);

        return Inertia::render('tenant/products/Sales', [
            'product' => [
                'id'          => $product->id,
                'name'        => $product->name,
                'ean'         => $product->ean,
                'codigo_erp'  => $product->codigo_erp,
                'image_url'   => $product->image_url ?? null,
            ],
            'sales'   => $sales,
            'totals'  => [
                'total_records'           => (int) ($totals?->total_records ?? 0),
                'total_quantity'          => (string) ($totals?->total_quantity ?? '0'),
                'total_value'             => (string) ($totals?->total_value ?? '0'),
                'total_acquisition_cost'  => (string) ($totals?->total_acquisition_cost ?? '0'),
                'total_profit_margin'     => (string) ($totals?->total_profit_margin ?? '0'),
                'total_margem_contribuicao' => (string) ($totals?->total_margem_contribuicao ?? '0'),
                'avg_sale_price'          => (string) ($totals?->avg_sale_price ?? '0'),
                'promo_records'           => (int) ($totals?->promo_records ?? 0),
                'promo_quantity'          => (string) ($totals?->promo_quantity ?? '0'),
                'promo_value'             => (string) ($totals?->promo_value ?? '0'),
                'regular_quantity'        => (string) ($totals?->regular_quantity ?? '0'),
                'regular_value'           => (string) ($totals?->regular_value ?? '0'),
            ],
            'filters' => [
                'sale_date_from' => $saleDateFrom,
                'sale_date_to'   => $saleDateTo,
                'promotion'      => $promotion,
                'store_id'       => $storeId,
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
        $product = Product::query()->whereKey($product)->firstOrFail();
        $this->authorize('delete', $product);

        $product->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.products.messages.deleted'),
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
