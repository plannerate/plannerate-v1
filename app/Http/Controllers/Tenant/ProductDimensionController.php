<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\DimensionStatus;
use App\Http\Controllers\Concerns\InteractsWithCategoryFilter;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\InteractsWithDeferredIndex;
use App\Http\Controllers\Tenant\Products\DimensionApprovalController;
use App\Http\Requests\Tenant\UpdateProductDimensionsRequest;
use App\Models\EanReference;
use App\Models\Product;
use App\Services\ProductDimensionService;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * @see DimensionApprovalController Aprovação e pipeline AI de dimensões
 */
class ProductDimensionController extends Controller
{
    use InteractsWithCategoryFilter;
    use InteractsWithDeferredIndex;
    use InteractsWithTenantContext;

    public function __construct(private readonly ProductDimensionService $service) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $search = $this->requestString($request, 'search');
        $categoryId = $this->requestString($request, 'category_id');
        $dimensionStatus = $this->requestEnum($request, 'dimension_publish_status', ['draft', 'published']);
        $requestedSort = trim((string) $request->query('sort', ''));
        $sort = in_array($requestedSort, ['name', 'ean', 'codigo_erp', 'dimension_publish_status', 'width', 'height', 'depth'], true) ? $requestedSort : null;
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
                'dimension_publish_status' => $dimensionStatus,
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

        Log::info('Dimensões: sync individual iniciado', [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'ean' => $product->ean,
            'dimension_status' => $product->dimension_status?->value,
        ]);

        $status = $this->fillProductDimensionsFromReference($product);

        if (in_array($status, ['missing_ean', 'reference_not_found'], true)) {
            Log::info('Dimensões: EAN sem referência — encaminhando para pesquisa AI', [
                'product_id' => $product->id,
                'motivo' => $status,
            ]);
            $this->service->research($product);
        }

        Log::info('Dimensões: sync individual concluído', [
            'product_id' => $product->id,
            'resultado' => $status,
        ]);

        Inertia::flash('toast', [
            'type' => $status === 'updated' ? 'success' : 'info',
            'message' => match ($status) {
                'updated' => 'Dimensões atualizadas a partir da referência EAN.',
                'already_configured' => 'Produto já possui dimensões configuradas. Nenhuma alteração realizada.',
                'missing_ean' => 'Produto sem EAN válido. Pesquisa AI iniciada.',
                default => 'Referência EAN não encontrada. Pesquisa AI iniciada.',
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

        Log::info('Dimensões: sync de página iniciado', [
            'total_produtos' => $productIds->count(),
            'product_ids' => $productIds->toArray(),
        ]);

        $products = Product::query()
            ->whereIn('id', $productIds->all())
            ->get()
            ->keyBy('id');

        $updatedCount = 0;
        $skippedConfiguredCount = 0;
        $researchedCount = 0;

        foreach ($productIds as $productId) {
            $product = $products->get($productId);

            if (! $product instanceof Product) {
                Log::warning('Dimensões: produto não encontrado na página', ['product_id' => $productId]);

                continue;
            }

            $this->authorize('update', $product);

            $status = $this->fillProductDimensionsFromReference($product);

            Log::debug('Dimensões: produto processado no sync de página', [
                'product_id' => $productId,
                'ean' => $product->ean,
                'resultado' => $status,
            ]);

            if ($status === 'updated') {
                $updatedCount++;

                continue;
            }

            if ($status === 'already_configured') {
                $skippedConfiguredCount++;

                continue;
            }

            // missing_ean ou reference_not_found: encaminha para pipeline AI
            $this->service->research($product);
            $researchedCount++;
        }

        Log::info('Dimensões: sync de página concluído', [
            'atualizados_por_ean' => $updatedCount,
            'ignorados_ja_configurados' => $skippedConfiguredCount,
            'encaminhados_para_ia' => $researchedCount,
        ]);

        $parts = [];
        if ($updatedCount > 0) {
            $parts[] = "{$updatedCount} atualizado(s) por EAN";
        }
        if ($skippedConfiguredCount > 0) {
            $parts[] = "{$skippedConfiguredCount} ignorado(s) (já configurados)";
        }
        if ($researchedCount > 0) {
            $parts[] = "{$researchedCount} encaminhado(s) para pesquisa AI";
        }

        Inertia::flash('toast', [
            'type' => $updatedCount > 0 || $researchedCount > 0 ? 'success' : 'info',
            'message' => 'Atualização concluída: '.implode(', ', $parts ?: ['nenhuma alteração']).'.',
        ]);

        return back();
    }

    private function fillProductDimensionsFromReference(Product $product): string
    {
        Log::debug('Dimensões: verificando configuração existente', [
            'product_id' => $product->id,
            'width' => $product->width,
            'height' => $product->height,
            'depth' => $product->depth,
            'dimension_status' => $product->dimension_status?->value,
        ]);

        if ($this->productHasConfiguredDimensions($product)) {
            $published = $this->publishConfiguredDimensions($product);

            Log::debug('Dimensões: produto já possui dimensões', [
                'product_id' => $product->id,
                'publicado_agora' => $published,
            ]);

            if ($published) {
                return 'updated';
            }

            return 'already_configured';
        }

        $normalizedEan = EanReference::normalizeEan((string) ($product->ean ?? ''));

        if ($normalizedEan === '') {
            Log::info('Dimensões: produto sem EAN — não é possível buscar referência', [
                'product_id' => $product->id,
                'name' => $product->name,
            ]);

            return 'missing_ean';
        }

        $reference = EanReference::query()
            ->forNormalizedEan($normalizedEan)
            ->whereNull('deleted_at')
            ->first();

        if (! $reference instanceof EanReference) {
            Log::info('Dimensões: EAN não encontrado em EanReference', [
                'product_id' => $product->id,
                'normalized_ean' => $normalizedEan,
            ]);

            return 'reference_not_found';
        }

        Log::debug('Dimensões: referência EAN encontrada', [
            'product_id' => $product->id,
            'ean_reference_id' => $reference->id,
            'ref_width' => $reference->width,
            'ref_height' => $reference->height,
            'ref_depth' => $reference->depth,
        ]);

        $updates = [];

        foreach (['width', 'height', 'depth', 'weight'] as $column) {
            if ($product->{$column} === null && $reference->{$column} !== null) {
                $updates[$column] = $reference->{$column};
            }
        }

        if (($product->unit === null || $product->unit === '') && is_string($reference->unit) && $reference->unit !== '') {
            $updates['unit'] = $reference->unit;
        }

        if ($updates === []) {
            Log::info('Dimensões: referência sem dimensões aplicáveis — encaminhando para AI', [
                'product_id' => $product->id,
                'ean' => $normalizedEan,
                'ref_width' => $reference->width,
                'ref_height' => $reference->height,
                'ref_depth' => $reference->depth,
            ]);

            return 'reference_without_dimensions';
        }

        Log::info('Dimensões: aplicando da referência EAN', [
            'product_id' => $product->id,
            'ean' => $normalizedEan,
            'campos_atualizados' => array_keys($updates),
            'valores' => array_intersect_key($updates, array_flip(['width', 'height', 'depth', 'weight', 'unit'])),
        ]);

        $updates['dimension_status'] = DimensionStatus::Approved;
        $updates['dimension_publish_status'] = 'published';
        $updates['status'] = 'published';
        $updates['has_dimensions'] = true;

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

        if ($product->dimension_publish_status !== 'published') {
            $updates['dimension_publish_status'] = 'published';
        }

        if (! $product->has_dimensions) {
            $updates['has_dimensions'] = true;
        }

        if ($product->dimension_status !== DimensionStatus::Approved) {
            $updates['dimension_status'] = DimensionStatus::Approved;
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
            ->when($dimensionStatus !== '', fn ($query) => $query->where('dimension_publish_status', $dimensionStatus))
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
                'dimension_publish_status' => $product->dimension_publish_status,
                // Campos do pipeline de pesquisa AI
                'ai_status' => $product->dimension_status?->value,
                'ai_status_label' => $product->dimension_status?->label(),
                'ai_status_color' => $product->dimension_status?->color(),
                'ai_source' => $product->dimension_source,
                'ai_source_url' => $product->dimension_source_url,
                'ai_confidence' => $product->dimension_confidence,
                'ai_reasoning' => $product->dimension_reasoning,
                'ai_warnings' => $product->dimension_warnings ?? [],
                'ai_researched_at' => $product->dimension_researched_at?->toIso8601String(),
            ]);
    }
}
