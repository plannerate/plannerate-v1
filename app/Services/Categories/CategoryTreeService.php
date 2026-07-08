<?php

namespace App\Services\Categories;

use App\Http\Controllers\Concerns\RunsInTenantContext;
use App\Models\Category;
use App\Models\Product;
use App\Models\Traits\HasCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Operações da tela de manutenção do mercadológico (árvore de categorias).
 *
 * Todos os métodos assumem que o contexto do tenant já está resolvido (conexão
 * `tenant`). No landlord isso é garantido pelo {@see RunsInTenantContext}.
 */
class CategoryTreeService
{
    public function __construct(
        private readonly CategoryHierarchyService $hierarchy,
    ) {}

    /**
     * Filhos diretos de `$parentId` (ou as raízes quando `null`), com as contagens
     * necessárias para o carregamento preguiçoso (lazy) da árvore no frontend.
     *
     * @return list<array{
     *     id: string,
     *     name: string,
     *     level_name: string|null,
     *     nivel: string|null,
     *     status: string,
     *     is_placeholder: bool,
     *     children_count: int,
     *     products_count: int,
     * }>
     */
    public function nodesForParent(?string $parentId): array
    {
        return Category::query()
            ->when(
                $parentId !== null && $parentId !== '',
                fn ($query) => $query->where('category_id', $parentId),
                fn ($query) => $query->whereNull('category_id'),
            )
            ->withCount(['children', 'products'])
            ->orderBy('name')
            ->get(['id', 'name', 'level_name', 'nivel', 'status', 'is_placeholder', 'category_id'])
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'level_name' => $category->level_name,
                'nivel' => $category->nivel,
                'status' => $category->status,
                'is_placeholder' => (bool) $category->is_placeholder,
                'children_count' => (int) $category->children_count,
                'products_count' => (int) $category->products_count,
            ])
            ->values()
            ->all();
    }

    /**
     * Reparenta a categoria `$categoryId` sob `$targetId` (ou para a raiz quando
     * `null`), validando a operação e recomputando a denormalização da subárvore.
     *
     * @throws ValidationException Quando o destino é inválido (ciclo, próprio nó ou
     *                             profundidade máxima excedida).
     */
    public function move(string $categoryId, ?string $targetId): Category
    {
        $category = Category::query()->findOrFail($categoryId);

        $target = null;
        if ($targetId !== null && $targetId !== '') {
            $target = Category::query()->findOrFail($targetId);
        }

        $this->guardAgainstInvalidMove($category, $target);

        return DB::transaction(function () use ($category, $target): Category {
            $category->forceFill(['category_id' => $target?->id])->save();

            $this->hierarchy->recomputeSubtree($category->refresh());

            return $category;
        });
    }

    /**
     * Move os produtos informados para a categoria `$targetId`.
     *
     * Não requer flush de cache: o cache de {@see HasCategory} é
     * indexado por `category_id` e guarda o caminho da própria categoria, que não
     * muda quando um produto troca de categoria.
     *
     * @param  list<string>  $productIds
     * @return int Quantidade de produtos efetivamente movidos.
     */
    public function moveProducts(array $productIds, string $targetId): int
    {
        $target = Category::query()->findOrFail($targetId);

        $productIds = array_values(array_filter(array_unique($productIds)));

        if ($productIds === []) {
            return 0;
        }

        return Product::query()
            ->whereIn('id', $productIds)
            ->update(['category_id' => $target->id]);
    }

    /**
     * Valida um reparent antes de executá-lo.
     *
     * @throws ValidationException
     */
    private function guardAgainstInvalidMove(Category $category, ?Category $target): void
    {
        if ($target === null) {
            return; // Mover para a raiz é sempre válido em termos de profundidade base.
        }

        if ($target->id === $category->id) {
            throw ValidationException::withMessages([
                'target_category_id' => __('app.landlord.mercadologico.errors.move_into_self'),
            ]);
        }

        $descendantIds = Category::getDescendantIds($category->id);
        if (in_array($target->id, $descendantIds, true)) {
            throw ValidationException::withMessages([
                'target_category_id' => __('app.landlord.mercadologico.errors.move_into_descendant'),
            ]);
        }

        $newRootDepth = $target->getMercadologicoDepth() + 1;
        $subtreeHeight = $this->subtreeHeight($category->id);

        if ($newRootDepth + ($subtreeHeight - 1) > CategoryHierarchyService::MAX_DEPTH) {
            throw ValidationException::withMessages([
                'target_category_id' => __('app.landlord.mercadologico.errors.max_depth_exceeded', [
                    'max' => CategoryHierarchyService::MAX_DEPTH,
                ]),
            ]);
        }
    }

    /**
     * Altura da subárvore (o próprio nó conta como 1), calculada apenas com
     * `id`/`category_id` para evitar carregar modelos completos.
     */
    private function subtreeHeight(string $rootId): int
    {
        $descendantIds = Category::getDescendantIds($rootId);

        /** @var array<string, string|null> $parentById */
        $parentById = Category::query()
            ->whereIn('id', $descendantIds)
            ->pluck('category_id', 'id')
            ->all();

        $childrenByParent = [];
        foreach ($parentById as $id => $parentId) {
            $childrenByParent[$parentId][] = $id;
        }

        // BFS a partir da raiz somando níveis.
        $height = 1;
        $queue = [[$rootId, 1]];
        while ($queue !== []) {
            [$id, $depth] = array_shift($queue);
            $height = max($height, $depth);

            foreach ($childrenByParent[$id] ?? [] as $childId) {
                $queue[] = [$childId, $depth + 1];
            }
        }

        return $height;
    }
}
