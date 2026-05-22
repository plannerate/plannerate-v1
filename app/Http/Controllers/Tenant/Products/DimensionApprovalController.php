<?php

namespace App\Http\Controllers\Tenant\Products;

use App\Enums\DimensionStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductDimensionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DimensionApprovalController extends Controller
{
    public function __construct(private readonly ProductDimensionService $service) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $status = $request->query('dimension_status', DimensionStatus::AwaitingApproval->value);
        $categoryId = (string) $request->query('category_id', '');
        $source = (string) $request->query('dimension_source', '');
        $confidence = (string) $request->query('dimension_confidence', '');

        $products = Product::query()
            ->when(
                in_array($status, array_column(DimensionStatus::cases(), 'value'), true),
                fn ($q) => $q->where('dimension_status', $status)
            )
            ->when($categoryId !== '', fn ($q) => $q->where('category_id', $categoryId))
            ->when($source !== '', fn ($q) => $q->where('dimension_source', $source))
            ->when($confidence !== '', fn ($q) => $q->where('dimension_confidence', $confidence))
            ->with('category:id,name')
            ->select([
                'id', 'name', 'ean', 'brand', 'category_id',
                'width', 'height', 'depth', 'weight', 'unit',
                'dimension_status', 'dimension_source', 'dimension_source_url',
                'dimension_confidence', 'dimension_reasoning', 'dimension_warnings',
                'dimension_researched_at', 'similar_to_product_id',
            ])
            ->latest('dimension_researched_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'ean' => $product->ean,
                'brand' => $product->brand,
                'category' => $product->category?->name,
                'category_id' => $product->category_id,
                'dimensions' => [
                    'width' => $product->width,
                    'height' => $product->height,
                    'depth' => $product->depth,
                    'weight' => $product->weight,
                    'unit' => $product->unit,
                ],
                'dimension_status' => $product->dimension_status?->value,
                'dimension_status_label' => $product->dimension_status?->label(),
                'dimension_status_color' => $product->dimension_status?->color(),
                'dimension_source' => $product->dimension_source,
                'dimension_source_url' => $product->dimension_source_url,
                'dimension_confidence' => $product->dimension_confidence,
                'dimension_reasoning' => $product->dimension_reasoning,
                'dimension_warnings' => $product->dimension_warnings ?? [],
                'dimension_researched_at' => $product->dimension_researched_at?->toIso8601String(),
                'similar_to_product_id' => $product->similar_to_product_id,
            ]);

        return Inertia::render('Products/PendingDimensionsApproval', [
            'products' => $products,
            'filters' => [
                'dimension_status' => $status,
                'category_id' => $categoryId,
                'dimension_source' => $source,
                'dimension_confidence' => $confidence,
            ],
            'statuses' => collect(DimensionStatus::cases())->map(fn (DimensionStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
            ])->all(),
        ]);
    }

    public function approve(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $this->service->approve($product, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dimensões aprovadas com sucesso.']);

        return back();
    }

    public function reject(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->service->reject($product, $request->user(), $validated['reason']);

        Inertia::flash('toast', ['type' => 'info', 'message' => 'Dimensões rejeitadas.']);

        return back();
    }

    public function research(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $this->service->research($product);

        Inertia::flash('toast', ['type' => 'info', 'message' => 'Pesquisa iniciada para este produto.']);

        return back();
    }

    public function approveAll(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', Product::class);

        $validated = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['required', 'string'],
        ]);

        $user = $request->user();
        $count = 0;
        Product::whereIn('id', $validated['product_ids'])
            ->where('dimension_confidence', 'high')
            ->where('dimension_status', DimensionStatus::AwaitingApproval)
            ->each(function (Product $product) use ($user, &$count): void {
                $this->service->approve($product, $user);
                $count++;
            });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$count} produto(s) aprovado(s) em lote.",
        ]);

        return back();
    }
}
