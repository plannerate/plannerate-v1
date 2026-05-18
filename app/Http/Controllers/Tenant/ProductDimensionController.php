<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\InteractsWithDeferredIndex;
use App\Http\Requests\Tenant\UpdateProductDimensionsRequest;
use App\Models\Product;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class ProductDimensionController extends Controller
{
    use InteractsWithDeferredIndex;
    use InteractsWithTenantContext;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $search = $this->requestString($request, 'search');
        $dimensionsStatus = $this->requestEnum($request, 'dimensions_status', ['draft', 'published']);
        $requestedSort = trim((string) $request->query('sort', ''));
        $sort = in_array($requestedSort, ['name', 'ean', 'codigo_erp', 'dimensions_status', 'width', 'height', 'depth'], true) ? $requestedSort : null;
        $requestedDirection = strtolower((string) $request->query('direction', 'asc'));
        $direction = in_array($requestedDirection, ['asc', 'desc'], true) ? $requestedDirection : 'asc';

        return $this->renderDeferredIndex('tenant/dimensions/Index', 'products', fn (): LengthAwarePaginator => $this->productsPaginator(
            $search,
            $dimensionsStatus,
            $sort,
            $direction,
            $this->resolvePerPage($request, 20),
        ), [
            'subdomain' => $this->tenantSubdomain(),
            'filters' => [
                'search' => $search,
                'dimensions_status' => $dimensionsStatus,
            ],
        ]);
    }

    public function update(UpdateProductDimensionsRequest $request, string $subdomain, string $product): JsonResponse
    {
        unset($subdomain);
        $product = Product::query()->whereKey($product)->firstOrFail();
        $this->authorize('update', $product);

        $product->update($request->validated());

        return response()->json([
            'id' => $product->id,
            'dimensions_ean' => $product->dimensions_ean,
            'width' => $product->width,
            'height' => $product->height,
            'depth' => $product->depth,
            'weight' => $product->weight,
            'unit' => $product->unit,
            'dimensions_status' => $product->dimensions_status,
            'dimensions_description' => $product->dimensions_description,
        ]);
    }

    private function productsPaginator(
        string $search,
        string $dimensionsStatus,
        ?string $sort,
        string $direction,
        int $perPage,
    ): LengthAwarePaginator {
        return Product::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($where) use ($search): void {
                    $where
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('ean', 'like', '%'.$search.'%')
                        ->orWhere('codigo_erp', 'like', '%'.$search.'%');
                });
            })
            ->when($dimensionsStatus !== '', fn ($query) => $query->where('dimensions_status', $dimensionsStatus))
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
                'dimensions_ean' => $product->dimensions_ean,
                'width' => $product->width,
                'height' => $product->height,
                'depth' => $product->depth,
                'weight' => $product->weight,
                'unit' => $product->unit,
                'dimensions_status' => $product->dimensions_status,
                'dimensions_description' => $product->dimensions_description,
            ]);
    }
}
