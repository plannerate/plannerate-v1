<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor;

use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Retorna a hierarquia de categorias (pais e filhos)
     * Se categoryId for fornecido, retorna a hierarquia dessa categoria
     * Se não, retorna as categorias raiz (sem pai)
     */
    public function index(Request $request, string $subdomain, ?string $categoryId = null)
    {
        if ($categoryId) {
            // Busca a categoria selecionada
            $category = Category::query()
                ->with(['parent', 'children'])
                ->find($categoryId);

            if (! $category) {
                return response()->json([
                    'error' => 'Categoria não encontrada',
                ], 404);
            }

            // Busca toda a hierarquia de pais (do mais alto até a categoria atual)
            $hierarchy = $category->getFullHierarchy();

            // Busca os filhos diretos da categoria
            $children = Category::query()
                ->where('category_id', $categoryId)
                ->whereNull('deleted_at')
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'current' => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'level_name' => $category->level_name,
                    'hierarchy_position' => $category->hierarchy_position,
                    'category_id' => $category->category_id,
                ],
                'hierarchy' => $hierarchy->map(function ($cat) {
                    return [
                        'id' => $cat->id,
                        'name' => $cat->name,
                        'slug' => $cat->slug,
                        'level_name' => $cat->level_name,
                        'hierarchy_position' => $cat->hierarchy_position,
                        'category_id' => $cat->category_id,
                    ];
                })->values(),
                'children' => $children->map(function ($child) {
                    return [
                        'id' => $child->id,
                        'name' => $child->name,
                        'slug' => $child->slug,
                        'level_name' => $child->level_name,
                        'hierarchy_position' => $child->hierarchy_position,
                        'category_id' => $child->category_id,
                        'has_children' => $child->children()->whereNull('deleted_at')->exists(),
                    ];
                })->values(),
            ]);
        }

        // Se não forneceu categoryId, retorna as categorias raiz (sem pai)
        $rootCategories = Category::query()
            ->whereNull('category_id')
            ->whereNull('deleted_at')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'current' => null,
            'hierarchy' => [],
            'children' => $rootCategories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'level_name' => $category->level_name,
                    'hierarchy_position' => $category->hierarchy_position,
                    'category_id' => $category->category_id,
                    'has_children' => $category->children()->whereNull('deleted_at')->exists(),
                ];
            })->values(),
        ]);
    }
}
