<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\InteractsWithPlanLimits;
use App\Http\Controllers\Concerns\InteractsWithTrashedFilter;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\InteractsWithDeferredIndex;
use App\Http\Requests\Tenant\SimilarGroupStoreRequest;
use App\Http\Requests\Tenant\SimilarGroupUpdateRequest;
use App\Models\Product;
use App\Models\SimilarGroup;
use App\Services\EanReferenceSimilarSyncService;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SimilarGroupController extends Controller
{
    use InteractsWithDeferredIndex;
    use InteractsWithPlanLimits;
    use InteractsWithTenantContext;
    use InteractsWithTrashedFilter;

    public function __construct(private readonly EanReferenceSimilarSyncService $eanReferenceSimilarSyncService) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SimilarGroup::class);

        $search = $this->requestString($request, 'search');
        $status = $this->requestEnum($request, 'status', ['draft', 'published']);
        $trashed = $this->resolveTrashedFilter($request);

        return $this->renderDeferredIndex('tenant/similar-groups/Index', 'similarGroups', fn (): LengthAwarePaginator => $this->similarGroupsPaginator(
            $search,
            $status,
            $trashed,
            $this->resolvePerPage($request, 10),
        ), [
            'filters' => [
                'search' => $search,
                'status' => $status,
                'trashed' => $trashed,
            ],
            'can' => $this->resolveCanCreate(SimilarGroup::class, 'similar_groups_limit', SimilarGroup::count()),
        ]);
    }

    private function similarGroupsPaginator(string $search, string $status, string $trashed, int $perPage): LengthAwarePaginator
    {
        $query = SimilarGroup::query()
            ->with(['products:id,name,ean,codigo_erp'])
            ->withCount('products');
        $this->applyTrashedToQuery($query, $trashed);

        return $query
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($where) use ($search): void {
                    $where
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('grouper_code', 'like', '%'.$search.'%');
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (SimilarGroup $group): array => [
                'id' => $group->id,
                'grouper_code' => $group->grouper_code,
                'name' => $group->name,
                'product_codes' => $this->productCodesForGroup($group),
                'products_count' => $group->products_count,
                'status' => $group->status,
                'created_at' => $group->created_at?->toDateTimeString(),
                'trashed' => $group->trashed(),
            ]);
    }

    public function productSearch(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $search = $this->requestString($request, 'search');

        return response()->json([
            'products' => $this->productOptions($search, 15),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', SimilarGroup::class);

        return Inertia::render('tenant/similar-groups/Form', [
            'similarGroup' => null,
            'productOptions' => $this->productOptions('', 12),
            'suggestedGrouperCode' => $this->suggestedGrouperCode(),
        ]);
    }

    public function store(SimilarGroupStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', SimilarGroup::class);

        $validated = $request->validated();
        $productIds = $this->validatedProductIds($validated);
        $products = $this->productsByIds($productIds);

        $similarGroup = SimilarGroup::query()->create([
            ...$this->groupAttributes($validated, $products),
            'user_id' => $request->user()?->getAuthIdentifier(),
        ]);
        $this->syncProducts($similarGroup, $productIds);
        $this->applyDimensionsToProducts($validated, $productIds);
        $this->syncEanReferenceSimilares($similarGroup, $products->pluck('ean')->all());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Grupo de similares criado com sucesso.',
        ]);

        return $this->toTenantRoute('tenant.similar-groups.index');
    }

    public function edit(SimilarGroup $similarGroup): Response
    {
        $this->authorize('update', $similarGroup);
        $similarGroup->load('products');

        return Inertia::render('tenant/similar-groups/Form', [
            'similarGroup' => [
                'id' => $similarGroup->id,
                'grouper_code' => $similarGroup->grouper_code,
                'name' => $similarGroup->name,
                'product_codes' => $this->productCodesForGroup($similarGroup),
                'base_dimensions_product_ean' => $similarGroup->base_dimensions_product_ean,
                'selected_products' => $similarGroup->products->map(fn (Product $product): array => $this->productOptionPayload($product))->all(),
                'dimensions' => $this->sharedDimensionsPayload($similarGroup),
                'status' => $similarGroup->status,
                'description' => $similarGroup->description,
            ],
            'productOptions' => $this->productOptions('', 12),
            'suggestedGrouperCode' => $this->suggestedGrouperCode(),
        ]);
    }

    public function update(SimilarGroupUpdateRequest $request, SimilarGroup $similarGroup): RedirectResponse
    {
        $this->authorize('update', $similarGroup);

        $validated = $request->validated();
        $productIds = $this->validatedProductIds($validated);
        $products = $this->productsByIds($productIds);
        $similarGroup->loadMissing('products');
        $previousGrouperCode = (string) $similarGroup->grouper_code;
        $previousEans = $similarGroup->products->pluck('ean')->all();

        $similarGroup->update($this->groupAttributes($validated, $products));
        $this->syncProducts($similarGroup, $productIds);
        $this->applyDimensionsToProducts($validated, $productIds);
        $this->syncEanReferenceSimilares($similarGroup, $products->pluck('ean')->all(), $previousGrouperCode, $previousEans);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Grupo de similares atualizado com sucesso.',
        ]);

        return $this->toTenantRoute('tenant.similar-groups.index');
    }

    public function destroy(SimilarGroup $similarGroup): RedirectResponse
    {
        $this->authorize('delete', $similarGroup);

        $this->eanReferenceSimilarSyncService->remove($similarGroup);

        if ($similarGroup->trashed()) {
            $similarGroup->forceDelete();

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('app.tenant.similar-groups.messages.force_deleted'),
            ]);

            return $this->toTenantRoute('tenant.similar-groups.index');
        }

        $similarGroup->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.similar-groups.messages.deleted'),
        ]);

        return $this->toTenantRoute('tenant.similar-groups.index');
    }

    public function restore(SimilarGroup $similarGroup): RedirectResponse
    {
        $this->authorize('delete', $similarGroup);

        $similarGroup->restore();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.similar-groups.messages.restored'),
        ]);

        return $this->toTenantRoute('tenant.similar-groups.index');
    }

    /**
     * @return list<array{id: string, name: string, ean: string|null, codigo_erp: string|null, brand: string|null, dimensions: array{width: mixed, height: mixed, depth: mixed, weight: mixed, unit: string|null, dimension_status: string|null}}>
     */
    private function productOptions(string $search, int $limit): array
    {
        return Product::query()
            ->select(['id', 'name', 'ean', 'codigo_erp', 'brand', 'width', 'height', 'depth', 'weight', 'unit', 'dimension_publish_status'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($where) use ($search): void {
                    $where
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('ean', 'like', '%'.$search.'%')
                        ->orWhere('codigo_erp', 'like', '%'.$search.'%')
                        ->orWhere('brand', 'like', '%'.$search.'%');
                });
            })
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Product $product): array => $this->productOptionPayload($product))
            ->all();
    }

    /**
     * @return array{id: string, name: string, ean: string|null, codigo_erp: string|null, brand: string|null, dimensions: array{width: mixed, height: mixed, depth: mixed, weight: mixed, unit: string|null, dimension_status: string|null}}
     */
    private function productOptionPayload(Product $product): array
    {
        return [
            'id' => (string) $product->id,
            'name' => (string) ($product->name ?: 'Produto sem nome'),
            'ean' => $product->ean,
            'codigo_erp' => $product->codigo_erp,
            'brand' => $product->brand,
            'dimensions' => [
                'width' => $product->width,
                'height' => $product->height,
                'depth' => $product->depth,
                'weight' => $product->weight,
                'unit' => $product->unit,
                'dimension_publish_status' => $product->dimension_publish_status,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<string>
     */
    private function validatedProductIds(array $validated): array
    {
        return collect(is_array($validated['product_ids'] ?? null) ? $validated['product_ids'] : [])
            ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $productIds
     * @return Collection<int, Product>
     */
    private function productsByIds(array $productIds)
    {
        return Product::query()
            ->whereKey($productIds)
            ->get(['id', 'ean', 'codigo_erp']);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  Collection<int, Product>  $products
     * @return array<string, mixed>
     */
    private function groupAttributes(array $validated, $products): array
    {
        return [
            'grouper_code' => $validated['grouper_code'],
            'name' => $validated['name'],
            'product_codes' => $products
                ->map(fn (Product $product): ?string => $product->ean ?: $product->codigo_erp)
                ->filter()
                ->values()
                ->all(),
            'base_dimensions_product_ean' => $this->baseDimensionsProductEan($validated, $products),
            'status' => $validated['status'],
            'description' => $validated['description'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  Collection<int, Product>  $products
     */
    private function baseDimensionsProductEan(array $validated, $products): ?string
    {
        $sourceProductId = $validated['dimension_source_product_id'] ?? null;

        if (! is_string($sourceProductId) || $sourceProductId === '') {
            return null;
        }

        $product = $products->first(fn (Product $product): bool => $product->getKey() === $sourceProductId);

        return $product?->ean;
    }

    /**
     * @param  list<string>  $productIds
     */
    private function syncProducts(SimilarGroup $similarGroup, array $productIds): void
    {
        $pivotValues = [];
        foreach ($productIds as $productId) {
            $pivotValues[$productId] = ['tenant_id' => (string) $this->tenantId()];
        }

        $similarGroup->products()->sync($pivotValues);
    }

    /**
     * @param  list<string|null>  $currentEans
     * @param  list<string|null>  $previousEans
     */
    private function syncEanReferenceSimilares(SimilarGroup $similarGroup, array $currentEans, ?string $previousGrouperCode = null, array $previousEans = []): void
    {
        $this->eanReferenceSimilarSyncService->sync(
            $similarGroup,
            $currentEans,
            $previousGrouperCode,
            $previousEans,
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  list<string>  $productIds
     */
    private function applyDimensionsToProducts(array $validated, array $productIds): void
    {
        if (($validated['apply_dimensions'] ?? false) !== true || $productIds === []) {
            return;
        }

        Product::query()
            ->whereKey($productIds)
            ->get(['id', 'tenant_id'])
            ->each(fn (Product $product) => $this->authorize('update', $product));

        $attributes = [
            'width' => $validated['width'] ?? null,
            'height' => $validated['height'] ?? null,
            'depth' => $validated['depth'] ?? null,
            'weight' => $validated['weight'] ?? null,
            'unit' => $validated['unit'] ?? 'cm',
            'dimension_publish_status' => $validated['dimension_publish_status'] ?? 'published',
        ];
        $attributes['has_dimensions'] = $this->hasDimensions($attributes);

        Product::query()
            ->whereKey($productIds)
            ->update($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function hasDimensions(array $attributes): bool
    {
        foreach (['width', 'height', 'depth'] as $field) {
            if ((float) ($attributes[$field] ?? 0) <= 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function productCodesForGroup(SimilarGroup $group): array
    {
        $codes = $group->relationLoaded('products')
            ? $group->products
                ->map(fn (Product $product): ?string => $product->ean ?: $product->codigo_erp)
                ->filter()
                ->values()
                ->all()
            : [];

        return $codes !== [] ? $codes : ($group->product_codes ?? []);
    }

    /**
     * @return array{width: mixed, height: mixed, depth: mixed, weight: mixed, unit: string|null, dimension_status: string|null}
     */
    private function sharedDimensionsPayload(SimilarGroup $similarGroup): array
    {
        $products = $similarGroup->products;

        return [
            'width' => $this->sharedProductValue($products, 'width'),
            'height' => $this->sharedProductValue($products, 'height'),
            'depth' => $this->sharedProductValue($products, 'depth'),
            'weight' => $this->sharedProductValue($products, 'weight'),
            'unit' => $this->sharedProductValue($products, 'unit') ?: 'cm',
            'dimension_publish_status' => $this->sharedProductValue($products, 'dimension_publish_status') ?: 'published',
        ];
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function sharedProductValue($products, string $field): mixed
    {
        $values = $products
            ->pluck($field)
            ->map(fn (mixed $value): mixed => $value === null ? null : (string) $value)
            ->unique()
            ->values();

        return $values->count() === 1 ? $values->first() : null;
    }

    private function suggestedGrouperCode(): string
    {
        for ($index = SimilarGroup::withTrashed()->count() + 1; $index < 10000; $index++) {
            $code = sprintf('SIM-%04d', $index);

            if (! SimilarGroup::withTrashed()->where('grouper_code', $code)->exists()) {
                return $code;
            }
        }

        return 'SIM-'.Str::upper(Str::random(8));
    }
}
