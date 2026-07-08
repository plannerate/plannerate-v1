<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Landlord\CategoryTreeController;
use App\Http\Requests\Tenant\MoveCategoryRequest;
use App\Http\Requests\Tenant\MoveProductsRequest;
use App\Http\Requests\Tenant\StoreCategoryNodeRequest;
use App\Http\Requests\Tenant\UpdateCategoryNodeRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\Categories\CategoryTreeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manutenção do mercadológico (árvore de categorias) dentro do próprio tenant.
 *
 * Mesma tela/experiência do landlord ({@see CategoryTreeController}),
 * porém operando diretamente sobre o tenant ativo (resolvido por host) — sem
 * seletor de tenant e sem troca de contexto. Reutiliza o mesmo serviço e os
 * mesmos componentes de frontend (URLs injetáveis).
 */
class MercadologicoController extends Controller
{
    public function __construct(
        private readonly CategoryTreeService $tree,
    ) {}

    /**
     * Página da árvore. Carrega apenas as raízes (nível 1); os filhos são
     * carregados sob demanda pelo endpoint {@see self::children()}.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', Category::class);

        return Inertia::render('tenant/mercadologico/Index', [
            'roots' => $this->tree->nodesForParent(null),
        ]);
    }

    /**
     * Filhos diretos de `?parent_id=` (ou raízes quando ausente). Alimenta o
     * carregamento preguiçoso da árvore no frontend.
     */
    public function children(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Category::class);

        $parentId = trim((string) $request->query('parent_id', ''));

        return response()->json([
            'nodes' => $this->tree->nodesForParent($parentId !== '' ? $parentId : null),
        ]);
    }

    /**
     * Produtos vinculados a uma categoria (paginado, com busca) para a janela.
     */
    public function products(Request $request, string $category): JsonResponse
    {
        $this->authorize('viewAny', Category::class);

        $search = trim((string) $request->query('search', ''));
        $perPage = $this->resolvePerPage($request, 25);

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

        return response()->json([
            'category' => [
                'id' => $categoryModel->id,
                'name' => $categoryModel->name,
                'full_path' => $categoryModel->full_path,
            ],
            'products' => $products,
        ]);
    }

    /**
     * Reparenta uma categoria (arraste na árvore).
     */
    public function move(MoveCategoryRequest $request, string $category): RedirectResponse
    {
        $categoryModel = Category::query()->findOrFail($category);
        $this->authorize('update', $categoryModel);

        $targetId = $request->input('target_category_id');

        $this->tree->move($category, $targetId !== null && $targetId !== '' ? (string) $targetId : null);

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
    public function store(StoreCategoryNodeRequest $request): JsonResponse
    {
        $this->authorize('create', Category::class);

        $validated = $request->validated();

        $node = $this->nodePayload(
            $this->tree->create($validated['parent_id'] ?? null, $validated),
        );

        return response()->json(['category' => $node]);
    }

    /**
     * Edita uma categoria (nome, código, status).
     */
    public function update(UpdateCategoryNodeRequest $request, string $category): JsonResponse
    {
        $categoryModel = Category::query()->findOrFail($category);
        $this->authorize('update', $categoryModel);

        $node = $this->nodePayload(
            $this->tree->update($category, $request->validated()),
        );

        return response()->json(['category' => $node]);
    }

    /**
     * Exclui (soft delete) uma categoria vazia.
     */
    public function destroy(string $category): JsonResponse
    {
        $categoryModel = Category::query()->findOrFail($category);
        $this->authorize('delete', $categoryModel);

        $this->tree->delete($category);

        return response()->json(['ok' => true]);
    }

    /**
     * Restaura uma categoria excluída (desfazer de exclusão/criação).
     */
    public function restore(string $category): JsonResponse
    {
        $this->authorize('create', Category::class);

        $node = $this->nodePayload($this->tree->restore($category));

        return response()->json(['category' => $node]);
    }

    /**
     * Move produtos de uma categoria para outra.
     */
    public function moveProducts(MoveProductsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $target = Category::query()->findOrFail($validated['target_category_id']);
        $this->authorize('update', $target);

        $moved = $this->tree->moveProducts($validated['product_ids'], (string) $target->id);

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
