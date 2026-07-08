<?php

namespace App\Services\Categories;

use App\Http\Controllers\Tenant\CategoryController;
use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Recalcula os campos denormalizados de hierarquia de uma subárvore de categorias.
 *
 * Os modelos mercadológicos guardam a posição na árvore de forma denormalizada
 * ({@see Category}: `nivel`, `level_name`, `hierarchy_position`, `full_path`,
 * `hierarchy_path`). Quando um nó é reparentado (drag & drop na árvore), esses
 * campos ficam obsoletos no nó e em TODOS os seus descendentes — este serviço os
 * reconstrói a partir do caminho do novo pai.
 *
 * Deve ser executado dentro do contexto do tenant (a conexão `tenant` já resolvida),
 * pois opera sobre a tabela `categories` do tenant atual.
 */
class CategoryHierarchyService
{
    /**
     * Profundidade máxima do mercadológico (mesmo limite da UI de cascata).
     *
     * @see CategoryController::MERCADOLOGICO_UI_LEVELS
     */
    public const MAX_DEPTH = 7;

    /**
     * Rótulos de cada nível pela profundidade (1 = raiz do tenant).
     * Fonte canônica espelhada de {@see \App\Models\Traits\HasCategory}.
     */
    public const LEVEL_LABELS = [
        1 => 'Segmento varejista',
        2 => 'Departamento',
        3 => 'Subdepartamento',
        4 => 'Categoria',
        5 => 'Subcategoria',
        6 => 'Segmento',
        7 => 'Subsegmento',
        8 => 'Atributo',
    ];

    /**
     * Recomputa a subárvore que tem `$root` como topo.
     *
     * Carrega o nó e todos os descendentes em memória (uma query), percorre em
     * largura a partir do caminho do pai de `$root` e grava os campos
     * denormalizados de cada nó dentro de uma transação. Ao final invalida os
     * caches de hierarquia mantidos por {@see \App\Models\Traits\HasCategory}.
     *
     * @return list<string> IDs de todas as categorias tocadas (raiz + descendentes)
     */
    public function recomputeSubtree(Category $root): array
    {
        $descendantIds = Category::getDescendantIds($root->id);

        /** @var Collection<string, Category> $nodes */
        $nodes = Category::query()
            ->whereIn('id', $descendantIds)
            ->get()
            ->keyBy('id');

        // Agrupa filhos por pai para navegar a árvore sem novas queries.
        $childrenByParent = $nodes->groupBy('category_id');

        $basePath = $this->resolveParentPath($root);

        DB::transaction(function () use ($root, $nodes, $childrenByParent, $basePath): void {
            $this->applyToBranch($root->id, $basePath, $nodes, $childrenByParent);
        });

        $this->flushHierarchyCache($descendantIds);

        return $descendantIds;
    }

    /**
     * Percorre iterativamente (BFS) a subárvore aplicando os campos denormalizados.
     *
     * @param  Collection<string, Category>  $nodes
     * @param  Collection<string, Collection<int, Category>>  $childrenByParent
     * @param  list<string>  $basePath  Nomes dos ancestrais do nó raiz (inclui o pai imediato)
     */
    private function applyToBranch(string $rootId, array $basePath, $nodes, $childrenByParent): void
    {
        // Fila de trabalho: cada item é [id do nó, caminho dos ancestrais].
        $queue = [[$rootId, $basePath]];

        while ($queue !== []) {
            [$id, $ancestorPath] = array_shift($queue);

            $node = $nodes->get($id);
            if (! $node instanceof Category) {
                continue;
            }

            $depth = count($ancestorPath) + 1;
            $path = [...$ancestorPath, (string) $node->name];

            $node->forceFill([
                'nivel' => (string) $depth,
                'hierarchy_position' => $depth,
                'level_name' => self::LEVEL_LABELS[$depth] ?? 'Nível '.$depth,
                'full_path' => implode(' > ', $path),
                'hierarchy_path' => $path,
            ])->save();

            foreach ($childrenByParent->get($id, collect()) as $child) {
                $queue[] = [$child->id, $path];
            }
        }
    }

    /**
     * Caminho de nomes dos ancestrais do nó raiz da subárvore (inclui o pai imediato).
     * Vazio quando o nó é raiz do tenant.
     *
     * @return list<string>
     */
    private function resolveParentPath(Category $root): array
    {
        if ($root->category_id === null) {
            return [];
        }

        $parent = Category::query()->whereKey($root->category_id)->first();

        if (! $parent instanceof Category) {
            return [];
        }

        if (is_array($parent->hierarchy_path) && $parent->hierarchy_path !== []) {
            return array_values(array_map('strval', $parent->hierarchy_path));
        }

        // Fallback para dados legados sem hierarchy_path preenchido.
        return $parent->getFullHierarchy()
            ->map(fn (Category $node): string => (string) $node->name)
            ->values()
            ->all();
    }

    /**
     * Invalida os caches de caminho/mercadológico que {@see HasCategory} mantém
     * por `category_id` (TTL de 2h), para todas as categorias tocadas.
     *
     * @param  list<string>  $categoryIds
     */
    private function flushHierarchyCache(array $categoryIds): void
    {
        foreach ($categoryIds as $categoryId) {
            cache()->forget("hierarchy_path:{$categoryId}");
            cache()->forget("mercadologico_cascading:{$categoryId}");
        }
    }
}
