<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\InteractsWithCategoryFilter;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\InteractsWithDeferredIndex;
use App\Http\Requests\Tenant\UpdateProductDimensionsRequest;
use App\Models\EanReference;
use App\Models\Product;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductDimensionController extends Controller
{
    use InteractsWithCategoryFilter;
    use InteractsWithDeferredIndex;
    use InteractsWithTenantContext;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $search = $this->requestString($request, 'search');
        $categoryId = $this->requestString($request, 'category_id');
        $dimensionStatus = $this->requestEnum($request, 'dimension_status', ['draft', 'published']);
        $requestedSort = trim((string) $request->query('sort', ''));
        $sort = in_array($requestedSort, ['name', 'ean', 'codigo_erp', 'dimension_status', 'width', 'height', 'depth'], true) ? $requestedSort : null;
        $requestedDirection = strtolower((string) $request->query('direction', 'asc'));
        $direction = in_array($requestedDirection, ['asc', 'desc'], true) ? $requestedDirection : 'asc';

        return $this->renderDeferredIndex('tenant/dimensions/Index', 'products', fn (): LengthAwarePaginator => $this->productsPaginator(
            $search,
            $categoryId,
            $dimensionStatus,
            $sort,
            $direction,
            $this->resolvePerPage($request, 20),
        ), [
            'filters' => [
                'search' => $search,
                'category_id' => $categoryId,
                'dimension_status' => $dimensionStatus,
            ],
        ]);
    }

    public function update(UpdateProductDimensionsRequest $request, string $product): RedirectResponse
    {
        $product = Product::query()->whereKey($product)->firstOrFail();
        $this->authorize('update', $product);

        $product->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dimensões atualizadas com sucesso.',
        ]);

        return $this->toTenantRoute('tenant.dimensions.index');
    }

    public function syncFromReference(string $product): RedirectResponse
    {
        $product = Product::query()->whereKey($product)->firstOrFail();
        $this->authorize('update', $product);

        $status = $this->fillProductDimensionsFromReference($product);

        Inertia::flash('toast', [
            'type' => $status === 'updated' ? 'success' : 'info',
            'message' => match ($status) {
                'updated' => 'Dimensões atualizadas a partir da referência EAN.',
                'already_configured' => 'Produto já possui dimensões configuradas. Nenhuma alteração realizada.',
                'missing_ean' => 'Produto sem EAN válido para buscar referência.',
                default => 'Referência EAN sem dimensões encontradas para este produto.',
            },
        ]);

        return back();
    }

    public function syncPageFromReference(Request $request): RedirectResponse
    {

        $this->authorize('viewAny', Product::class);

        $validated = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['required'],
        ]);

        $productIds = collect($validated['product_ids'])
            ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
            ->values();

        $products = Product::query()
            ->whereIn('id', $productIds->all())
            ->get()
            ->keyBy('id');

        $updatedCount = 0;
        $skippedConfiguredCount = 0;

        foreach ($productIds as $productId) {
            $product = $products->get($productId);

            if (! $product instanceof Product) {
                continue;
            }

            $this->authorize('update', $product);

            $status = $this->fillProductDimensionsFromReference($product);

            if ($status === 'updated') {
                $updatedCount++;

                continue;
            }

            if ($status === 'already_configured') {
                $skippedConfiguredCount++;
            }
        }

        Inertia::flash('toast', [
            'type' => $updatedCount > 0 ? 'success' : 'info',
            'message' => sprintf(
                'Atualização por EAN concluída: %d produto(s) atualizado(s), %d ignorado(s) por já possuírem dimensões.',
                $updatedCount,
                $skippedConfiguredCount,
            ),
        ]);

        return back();
    }

    private function fillProductDimensionsFromReference(Product $product): string
    {
        if ($this->productHasConfiguredDimensions($product)) {
            if ($this->publishConfiguredDimensions($product)) {
                return 'updated';
            }

            return 'already_configured';
        }

        $normalizedEan = EanReference::normalizeEan((string) ($product->ean ?? ''));

        if ($normalizedEan === '') {
            return 'missing_ean';
        }

        $reference = EanReference::query()
            ->forNormalizedEan($normalizedEan)
            ->whereNull('deleted_at')
            ->first();

        if (! $reference instanceof EanReference) {
            return 'reference_not_found';
        }

        $updates = [];

        foreach (['width', 'height', 'depth', 'weight'] as $column) {
            if ($product->{$column} === null && $reference->{$column} !== null) {
                $updates[$column] = $reference->{$column};
            }
        }

        if (($product->unit === null || $product->unit === '') && is_string($reference->unit) && $reference->unit !== '') {
            $updates['unit'] = $reference->unit;
        }

        $updates['dimension_status'] = 'published';
        $updates['status'] = 'published';
        $updates['has_dimensions'] = true;

        if ($updates === []) {
            return 'reference_without_dimensions';
        }

        $product->update($updates);

        return 'updated';
    }

    private function productHasConfiguredDimensions(Product $product): bool
    {
        foreach (['width', 'height', 'depth'] as $column) {
            $value = $product->{$column};

            if ($value !== null && trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function publishConfiguredDimensions(Product $product): bool
    {
        $updates = [];
        if ($product->status == 'draft') {
            $updates['status'] = 'published';
        }

        if ($product->dimension_status !== 'published') {
            $updates['dimension_status'] = 'published';
        }

        if (! $product->has_dimensions) {
            $updates['has_dimensions'] = true;
        }

        if ($updates === []) {
            return false;
        }

        $product->update($updates);

        return true;
    }

    private function productsPaginator(
        string $search,
        string $categoryId,
        string $dimensionStatus,
        ?string $sort,
        string $direction,
        int $perPage,
    ): LengthAwarePaginator {
        $categoryIds = $this->categoryAndDescendantIds($categoryId);

        return Product::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($where) use ($search): void {
                    $where
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('ean', 'like', '%'.$search.'%')
                        ->orWhere('codigo_erp', 'like', '%'.$search.'%');
                });
            })
            ->when($categoryIds !== [], fn ($query) => $query->whereIn('category_id', $categoryIds))
            ->when($dimensionStatus !== '', fn ($query) => $query->where('dimension_status', $dimensionStatus))
            ->when(
                $sort !== null,
                fn ($query) => $query->orderBy($sort, $direction),
                fn ($query) => $query->latest(),
            )
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'ean' => $product->ean,
                'codigo_erp' => $product->codigo_erp,
                'width' => $product->width,
                'height' => $product->height,
                'depth' => $product->depth,
                'weight' => $product->weight,
                'unit' => $product->unit,
                'dimension_status' => $product->dimension_status,
            ]);
    }
}
