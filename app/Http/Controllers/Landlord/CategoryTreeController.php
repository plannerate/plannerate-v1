<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Concerns\RunsInTenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\MoveCategoryRequest;
use App\Http\Requests\Landlord\MoveProductsRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\Categories\CategoryTreeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manutenção do mercadológico (árvore de categorias) a partir do landlord,
 * operando sobre um tenant escolhido. Toda leitura/escrita acontece dentro do
 * contexto do tenant via {@see RunsInTenantContext}.
 */
class CategoryTreeController extends Controller
{
    use RunsInTenantContext;

    public function __construct(
        private readonly CategoryTreeService $tree,
    ) {}

    /**
     * Página da árvore. Carrega apenas as raízes (nível 1); os filhos são
     * carregados sob demanda pelo endpoint {@see self::children()}.
     */
    public function index(Tenant $tenant): Response
    {
        $this->authorize('update', $tenant);

        $roots = $this->runInTenantContext($tenant, fn (): array => $this->tree->nodesForParent(null));

        return Inertia::render('landlord/tenants/mercadologico/Index', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'roots' => $roots,
        ]);
    }

    /**
     * Filhos diretos de `?parent_id=` (ou raízes quando ausente). Alimenta o
     * carregamento preguiçoso da árvore no frontend.
     */
    public function children(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('update', $tenant);

        $parentId = trim((string) $request->query('parent_id', ''));

        $nodes = $this->runInTenantContext(
            $tenant,
            fn (): array => $this->tree->nodesForParent($parentId !== '' ? $parentId : null),
        );

        return response()->json(['nodes' => $nodes]);
    }

    /**
     * Produtos vinculados a uma categoria (paginado, com busca) para a modal.
     */
    public function products(Request $request, Tenant $tenant, string $category): JsonResponse
    {
        $this->authorize('update', $tenant);

        $search = trim((string) $request->query('search', ''));
        $perPage = $this->resolvePerPage($request, 25);

        $data = $this->runInTenantContext($tenant, function () use ($category, $search, $perPage): array {
            $categoryModel = Category::query()->findOrFail($category);

            $products = Product::query()
                ->where('category_id', $categoryModel->id)
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($where) use ($search): void {
                        $where->where('name', 'like', '%'.$search.'%')
                            ->orWhere('ean', 'like', '%'.$search.'%')
                            ->orWhere('codigo_erp', 'like', '%'.$search.'%');
                    });
                })
                ->orderBy('name')
                ->paginate($perPage)
                ->withQueryString()
                ->through(fn (Product $product): array => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'ean' => $product->ean,
                    'codigo_erp' => $product->codigo_erp,
                    'image_url' => $product->image_url,
                ]);

            return [
                'category' => [
                    'id' => $categoryModel->id,
                    'name' => $categoryModel->name,
                    'full_path' => $categoryModel->full_path,
                ],
                'products' => $products,
            ];
        });

        return response()->json($data);
    }

    /**
     * Reparenta uma categoria (arraste na árvore).
     */
    public function move(MoveCategoryRequest $request, Tenant $tenant, string $category): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $targetId = $request->input('target_category_id');

        $this->runInTenantContext($tenant, function () use ($category, $targetId): void {
            $this->tree->move($category, $targetId !== null && $targetId !== '' ? (string) $targetId : null);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.mercadologico.messages.moved'),
        ]);

        return back();
    }

    /**
     * Move produtos de uma categoria para outra.
     */
    public function moveProducts(MoveProductsRequest $request, Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $validated = $request->validated();

        $moved = $this->runInTenantContext($tenant, fn (): int => $this->tree->moveProducts(
            $validated['product_ids'],
            (string) $validated['target_category_id'],
        ));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice('app.landlord.mercadologico.messages.products_moved', $moved, ['count' => $moved]),
        ]);

        return back();
    }
}
