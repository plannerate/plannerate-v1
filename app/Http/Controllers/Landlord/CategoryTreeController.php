<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Concerns\RunsInTenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\MoveCategoryRequest;
use App\Http\Requests\Landlord\MoveProductsRequest;
use App\Http\Requests\Landlord\StoreCategoryNodeRequest;
use App\Http\Requests\Landlord\UpdateCategoryNodeRequest;
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
     * Cria uma categoria (raiz ou subcategoria). Responde JSON (chamado via
     * useHttp) com o nó pronto para inserir na árvore.
     */
    public function store(StoreCategoryNodeRequest $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('update', $tenant);

        $validated = $request->validated();

        $node = $this->runInTenantContext($tenant, fn (): array => $this->nodePayload(
            $this->tree->create($validated['parent_id'] ?? null, $validated),
        ));

        return response()->json(['category' => $node]);
    }

    /**
     * Edita uma categoria (nome, código, status).
     */
    public function update(UpdateCategoryNodeRequest $request, Tenant $tenant, string $category): JsonResponse
    {
        $this->authorize('update', $tenant);

        $validated = $request->validated();

        $node = $this->runInTenantContext($tenant, fn (): array => $this->nodePayload(
            $this->tree->update($category, $validated),
        ));

        return response()->json(['category' => $node]);
    }

    /**
     * Exclui (soft delete) uma categoria vazia.
     */
    public function destroy(Tenant $tenant, string $category): JsonResponse
    {
        $this->authorize('update', $tenant);

        $this->runInTenantContext($tenant, function () use ($category): void {
            $this->tree->delete($category);
        });

        return response()->json(['ok' => true]);
    }

    /**
     * Restaura uma categoria excluída (desfazer de exclusão/criação).
     */
    public function restore(Tenant $tenant, string $category): JsonResponse
    {
        $this->authorize('update', $tenant);

        $node = $this->runInTenantContext($tenant, fn (): array => $this->nodePayload(
            $this->tree->restore($category),
        ));

        return response()->json(['category' => $node]);
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

    /**
     * Serializa uma categoria no formato de nó da árvore (mesma forma do
     * `nodesForParent`).
     *
     * @return array{
     *     id: string, name: string, level_name: string|null, nivel: string|null,
     *     codigo: int|null, status: string, is_placeholder: bool,
     *     children_count: int, products_count: int
     * }
     */
    private function nodePayload(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'level_name' => $category->level_name,
            'nivel' => $category->nivel,
            'codigo' => $category->codigo,
            'status' => $category->status,
            'is_placeholder' => (bool) $category->is_placeholder,
            'children_count' => $category->children()->count(),
            'products_count' => $category->products()->count(),
        ];
    }
}
