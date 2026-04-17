<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\HasClientVisibilityRule;
use App\Http\Requests\Tenant\MercadologicoMoveRequest;
use App\Http\Requests\Tenant\MercadologicoProductsMoveRequest;
use App\Http\Requests\Tenant\MercadologicoStoreRequest;
use App\Http\Requests\Tenant\MercadologicoUpdateRequest;
use App\Jobs\ReorganizaCategoriasComIaJob;
use App\Models\Client;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Category;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\MercadologicoReorganizeLog;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Planogram;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use App\Services\ReorganizaCategoriasComIa;
use Callcocam\LaravelRaptor\Http\Controllers\ResourceController;
use Callcocam\LaravelRaptor\Support\Pages\Execute;
use Callcocam\LaravelRaptor\Support\Pages\Index;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MercadologicoController extends ResourceController
{
    use HasClientVisibilityRule;

    protected Closure|string|null $maxWidth = '7xl';

    public function getPages(): array
    {
        return [
            'index' => Index::route('/mercadologico')
                ->label('Mercadológico')
                ->name('mercadologico.index')
                ->icon('FolderTree')
                ->group('Catálogo')
                ->groupCollapsible(true)
                ->visible(fn () => $this->hasCurrentClientContext())
                ->order(17)
                ->middlewares(['auth', 'verified']),
            'move' => Execute::route('/mercadologico/move')
                ->method('PATCH')
                ->action('move')
                ->name('mercadologico.move')
                ->middlewares(['auth', 'verified']),
            'destroy' => Execute::route('/mercadologico/category/destroy')
                ->method('DELETE')
                ->action('destroy')
                ->name('mercadologico.destroy')
                ->middlewares(['auth', 'verified']),
            'store' => Execute::route('/mercadologico/category/store')
                ->method('POST')
                ->action('store')
                ->name('mercadologico.store')
                ->middlewares(['auth', 'verified']),
            'duplicate' => Execute::route('/mercadologico/category/duplicate')
                ->method('POST')
                ->action('duplicate')
                ->name('mercadologico.duplicate')
                ->middlewares(['auth', 'verified']),
            'update' => Execute::route('/mercadologico/category/update')
                ->method('PATCH')
                ->action('update')
                ->name('mercadologico.update')
                ->middlewares(['auth', 'verified']),
            'reorganize' => Execute::route('/mercadologico/reorganize')
                ->method('POST')
                ->action('reorganize')
                ->name('mercadologico.reorganize')
                ->middlewares(['auth', 'verified']),
            'reorganize_apply' => Execute::route('/mercadologico/reorganize/apply')
                ->method('POST')
                ->action('reorganizeApply')
                ->name('mercadologico.reorganize.apply')
                ->middlewares(['auth', 'verified']),
            'reorganize_restore' => Execute::route('/mercadologico/reorganize/restore')
                ->method('POST')
                ->action('reorganizeRestore')
                ->name('mercadologico.reorganize.restore')
                ->middlewares(['auth', 'verified']),
            'products' => Execute::route('/mercadologico/products')
                ->method('GET')
                ->action('products')
                ->name('mercadologico.products')
                ->middlewares(['auth', 'verified']),
            'products_move' => Execute::route('/mercadologico/products/move')
                ->method('PATCH')
                ->action('moveProducts')
                ->name('mercadologico.products.move')
                ->middlewares(['auth', 'verified']),
        ];
    }

    protected function resourcePath(): ?string
    {
        return null;
    }

    /**
     * Redirect para o index do mercadológico preservando expand/selected vindos do request (enviados pelo front nos POST/PATCH).
     */
    private function redirectToMercadologicoIndex(Request $request, string $message, string $flashKey = 'success'): RedirectResponse
    {
        $query = array_filter([
            'expand' => $request->input('expand') ?? $request->query('expand'),
            'selected' => $request->input('selected') ?? $request->query('selected'),
        ], fn ($v) => $v !== null && $v !== '');

        $url = route('tenant.mercadologico.index');
        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        return redirect($url)->with($flashKey, $message);
    }

    /**
     * IDs de nós expandidos (estado da UI, preservado via URL).
     *
     * @return array<int, string>
     */
    protected function getExpandIdsFromRequest(Request $request): array
    {
        $expand = $request->query('expand');

        if ($expand === null || $expand === '') {
            return [];
        }

        return array_values(array_filter(
            is_array($expand) ? $expand : explode(',', (string) $expand),
            fn (string $id) => $id !== '',
        ));
    }

    /**
     * Carrega raízes + filhos dos nós expandidos em 1 query (sem N+1).
     *
     * @param  array<int, string>  $expandIds
     * @return array<int, array<string, mixed>>
     */
    protected function getCategoriesTree(array $expandIds = []): array
    {
        $allCategories = Category::query()
            ->where(function ($q) use ($expandIds): void {
                $q->whereNull('category_id');
                if ($expandIds !== []) {
                    $q->orWhereIn('category_id', $expandIds);
                }
            })
            ->withCount(['children', 'products', 'planograms'])
            ->orderBy('hierarchy_position')
            ->orderBy('name')
            ->get();

        $byParent = [];
        $nameMap = [];
        $parentIdMap = [];

        foreach ($allCategories as $cat) {
            $key = $cat->category_id ?? '__roots__';
            $byParent[$key][] = $cat;
            $nameMap[$cat->id] = $cat->name;
            $parentIdMap[$cat->id] = $cat->category_id;
        }

        $computeFullPath = function (string $categoryId) use ($nameMap, $parentIdMap): string {
            $parts = [];
            $current = $categoryId;
            $visited = [];
            while ($current !== null && ! in_array($current, $visited, true)) {
                $visited[] = $current;
                $parts[] = $nameMap[$current] ?? '';
                $current = $parentIdMap[$current] ?? null;
            }

            return implode(' > ', array_reverse($parts));
        };

        $expandSet = array_flip($expandIds);

        $buildNode = function (Category $cat, int $depth) use (&$buildNode, $byParent, $expandSet, $computeFullPath): array {
            $includeChildren = isset($expandSet[$cat->id]) && isset($byParent[$cat->id]);

            return [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'depth' => $depth,
                'nivel' => $cat->nivel,
                'hierarchy_position' => $cat->hierarchy_position,
                'status' => $cat->status,
                'level_name' => $cat->level_name,
                'full_path' => $computeFullPath($cat->id),
                'children_count' => $cat->children_count ?? 0,
                'products_count' => $cat->products_count ?? 0,
                'planograms_count' => $cat->planograms_count ?? 0,
                'children' => $includeChildren
                    ? array_values(array_map(
                        fn (Category $child) => $buildNode($child, $depth + 1),
                        $byParent[$cat->id],
                    ))
                    : [],
            ];
        };

        return array_values(array_map(
            fn (Category $root) => $buildNode($root, 1),
            $byParent['__roots__'] ?? [],
        ));
    }

    public function index(Request $request): Response
    {
        $expandIds = $this->getExpandIdsFromRequest($request);
        $selectedId = $request->query('selected') ?: null;

        $reorganizeLogs = MercadologicoReorganizeLog::query()
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (MercadologicoReorganizeLog $log) => [
                'id' => $log->id,
                'status' => $log->status,
                'applied_at' => $log->applied_at?->toIso8601String(),
                'created_at' => $log->created_at->toIso8601String(),
                'agent_response' => $log->agent_response,
            ]);

        $reorganizeLog = session('reorganize_log');
        $requestedLogId = $request->query('reorganize_log');
        if ($requestedLogId && $reorganizeLogs->isNotEmpty()) {
            $found = $reorganizeLogs->firstWhere('id', $requestedLogId);
            if ($found) {
                $reorganizeLog = $found;
            }
        }

        return Inertia::render('tenant/mercadologico/index', [
            'title' => 'Mercadológico',
            'categories' => fn () => $this->getCategoriesTree($expandIds),
            'expand' => $expandIds,
            'selected' => $selectedId,
            'hierarchy_level_names' => self::HIERARCHY_LEVEL_NAMES,
            'move_url' => route('tenant.mercadologico.move'),
            'destroy_url' => route('tenant.mercadologico.destroy'),
            'store_url' => route('tenant.mercadologico.store'),
            'duplicate_url' => route('tenant.mercadologico.duplicate'),
            'update_url' => route('tenant.mercadologico.update'),
            'reorganize_url' => route('tenant.mercadologico.reorganize'),
            'reorganize_apply_url' => route('tenant.mercadologico.reorganize.apply'),
            'reorganize_restore_url' => route('tenant.mercadologico.reorganize.restore'),
            'products_url' => route('tenant.mercadologico.products'),
            'products_move_url' => route('tenant.mercadologico.products.move'),
            'reorganize_logs' => $reorganizeLogs,
            'reorganize_log' => $reorganizeLog,
        ]);
    }

    public function move(MercadologicoMoveRequest $request): RedirectResponse
    {
        $categoryId = $request->validated('id');
        $newParentId = $request->validated('category_id');

        $category = Category::query()->findOrFail($categoryId);

        if ($newParentId !== null) {
            $newParent = Category::query()->findOrFail($newParentId);
            if (in_array($category->id, $newParent->getHierarchyIds(), true)) {
                return back()->withErrors(['category_id' => 'Não é possível mover uma categoria para dentro de si mesma ou de um descendente.']);
            }
        }

        DB::transaction(function () use ($category, $newParentId): void {
            $category->update(['category_id' => $newParentId]);
        });

        return $this->redirectToMercadologicoIndex($request, 'Categoria movida com sucesso.');
    }

    /**
     * Exclui a categoria após validar que não tem filhos nem vínculos.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $categoryId = $request->query('id') ?? $request->input('id');
        if (! $categoryId) {
            return back()->withErrors(['id' => 'ID da categoria é obrigatório.']);
        }

        $category = Category::query()->find($categoryId);
        if (! $category) {
            return back()->withErrors(['id' => 'Categoria não encontrada.']);
        }

        $childrenCount = $category->children()->count();
        $productsCount = Product::query()->where('category_id', $category->id)->count();
        $planogramsCount = Planogram::query()->where('category_id', $category->id)->count();

        if ($childrenCount > 0 || $productsCount > 0 || $planogramsCount > 0) {
            $reasons = [];
            if ($childrenCount > 0) {
                $reasons[] = "possui {$childrenCount} subcategoria(s)";
            }
            if ($productsCount > 0) {
                $reasons[] = "está relacionada a {$productsCount} produto(s)";
            }
            if ($planogramsCount > 0) {
                $reasons[] = "está relacionada a {$planogramsCount} planograma(s)";
            }

            return back()->withErrors([
                'destroy' => 'Não é possível excluir esta categoria: '.implode(', ', $reasons).'.',
            ]);
        }

        $category->delete();

        return $this->redirectToMercadologicoIndex($request, 'Categoria excluída com sucesso.');
    }

    /**
     * Nomes dos níveis hierárquicos (espelhando HasCategory).
     *
     * @var array<int, string>
     */
    private const HIERARCHY_LEVEL_NAMES = [
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
     * Cria uma nova subcategoria (ou raiz se category_id for null).
     */
    public function store(MercadologicoStoreRequest $request): RedirectResponse
    {
        $name = $request->validated('name');
        $parentId = $request->validated('category_id');

        $slug = $this->uniqueSlugForName($name);

        DB::transaction(function () use ($name, $slug, $parentId): void {
            $parent = $parentId ? Category::query()->find($parentId) : null;
            $depth = $parent ? $parent->getFullHierarchy()->count() + 1 : 1;
            $depth = min($depth, 8);

            $levelName = self::HIERARCHY_LEVEL_NAMES[$depth] ?? 'nivel_'.$depth;
            $levelNameSlug = Str::slug($levelName, '_');

            $maxPosition = Category::query()
                ->where('category_id', $parentId)
                ->max('hierarchy_position');

            Category::query()->create([
                'name' => $name,
                'slug' => $slug,
                'category_id' => $parentId,
                'nivel' => (string) $depth,
                'level_name' => $levelNameSlug,
                'hierarchy_position' => ($maxPosition ?? 0) + 1,
                'status' => 'draft',
            ]);
        });

        return $this->redirectToMercadologicoIndex($request, 'Categoria criada com sucesso.');
    }

    /**
     * Gera um slug único a partir do nome.
     */
    private function uniqueSlugForName(string $name, ?string $excludeId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'categoria';
        }
        $slug = $base;
        $n = 0;
        while (Category::query()->where('slug', $slug)->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))->exists()) {
            $n++;
            $slug = $base.'-'.$n;
        }

        return $slug;
    }

    /**
     * Atualiza nome e/ou slug de uma categoria.
     */
    public function update(MercadologicoUpdateRequest $request): RedirectResponse
    {
        $category = Category::query()->findOrFail($request->validated('id'));

        $slug = $request->validated('slug') ?? $this->uniqueSlugForName($request->validated('name'), $category->id);

        DB::transaction(function () use ($category, $request, $slug): void {
            $category->update([
                'name' => $request->validated('name'),
                'slug' => $slug,
            ]);
        });

        return $this->redirectToMercadologicoIndex($request, 'Categoria atualizada com sucesso.');
    }

    /**
     * Duplica uma categoria (mesmo pai, nome com " (cópia)").
     */
    public function duplicate(Request $request): RedirectResponse
    {
        $categoryId = $request->input('id') ?? $request->query('id');
        if (! $categoryId) {
            return back()->withErrors(['id' => 'ID da categoria é obrigatório.']);
        }

        $category = Category::query()->find($categoryId);
        if (! $category) {
            return back()->withErrors(['id' => 'Categoria não encontrada.']);
        }

        $newName = $category->name.' (cópia)';
        $slug = $this->uniqueSlugForName(Str::slug($category->name).'-copia');

        DB::transaction(function () use ($category, $newName, $slug): void {
            $maxPosition = Category::query()
                ->where('category_id', $category->category_id)
                ->max('hierarchy_position');

            Category::query()->create([
                'name' => $newName,
                'slug' => $slug,
                'category_id' => $category->category_id,
                'nivel' => $category->nivel,
                'level_name' => $category->level_name,
                'hierarchy_position' => ($maxPosition ?? 0) + 1,
                'status' => 'draft',
            ]);
        });

        return $this->redirectToMercadologicoIndex($request, 'Categoria duplicada com sucesso.');
    }

    /**
     * Enfileira a geração da sugestão de reorganização via IA. O usuário será notificado quando estiver pronto.
     */
    public function reorganize(Request $request): RedirectResponse
    {
        $userId = $request->user()?->id;
        $clientId = config('app.current_client_id');
        $clientDatabase = null;
        $clientTenantId = null;
        if ($clientId) {
            $client = Client::on(config('raptor.database.landlord_connection_name', 'landlord'))->find($clientId);
            if ($client) {
                $clientDatabase = $client->database;
                $clientTenantId = $client->tenant_id;
            }
        }

        $mercadologicoIndexUrl = route('tenant.mercadologico.index');

        ReorganizaCategoriasComIaJob::dispatch($userId, $clientId, $clientDatabase, $clientTenantId, $mercadologicoIndexUrl);

        return $this->redirectToMercadologicoIndex($request, 'Reorganização em andamento. Você será notificado quando a sugestão estiver pronta.');
    }

    /**
     * Aplica a sugestão de um log (renames + merges).
     */
    public function reorganizeApply(Request $request): RedirectResponse
    {
        $logId = $request->input('log_id');
        if (! $logId) {
            return back()->withErrors(['log_id' => 'ID do log é obrigatório.']);
        }

        $log = MercadologicoReorganizeLog::query()->find($logId);
        if (! $log) {
            return back()->withErrors(['log_id' => 'Log não encontrado.']);
        }

        $service = new ReorganizaCategoriasComIa;
        $result = $service->aplicar($log);

        $message = 'Sugestão aplicada.';
        $hasCounts = ($result['renames_count'] ?? 0) > 0
            || ($result['merges_count'] ?? 0) > 0
            || ($result['disable_count'] ?? 0) > 0
            || ($result['delete_count'] ?? 0) > 0;
        if ($hasCounts) {
            $parts = [];
            if (($result['renames_count'] ?? 0) > 0) {
                $parts[] = $result['renames_count'].' nome(s) padronizado(s)';
            }
            if (($result['merges_count'] ?? 0) > 0) {
                $parts[] = $result['merges_count'].' duplicata(s) fundida(s)';
            }
            if (($result['disable_count'] ?? 0) > 0) {
                $parts[] = $result['disable_count'].' desabilitada(s) (draft)';
            }
            if (($result['delete_count'] ?? 0) > 0) {
                $parts[] = $result['delete_count'].' excluída(s) (soft)';
            }
            $message = 'Sugestão aplicada: '.implode(', ', $parts).'.';
        }

        return $this->redirectToMercadologicoIndex($request, $message);
    }

    /**
     * Restaura o estado a partir do backup do log.
     */
    public function reorganizeRestore(Request $request): RedirectResponse
    {
        $logId = $request->input('log_id');
        if (! $logId) {
            return back()->withErrors(['log_id' => 'ID do log é obrigatório.']);
        }

        $log = MercadologicoReorganizeLog::query()->find($logId);
        if (! $log) {
            return back()->withErrors(['log_id' => 'Log não encontrado.']);
        }

        $service = new ReorganizaCategoriasComIa;
        $service->restaurar($log);

        return $this->redirectToMercadologicoIndex($request, 'Backup restaurado com sucesso.');
    }

    /**
     * Lista produtos da categoria (JSON para o modal).
     */
    public function products(Request $request): JsonResponse
    {
        $categoryId = $request->query('category_id');
        if (! $categoryId || ! is_string($categoryId)) {
            return response()->json(['error' => 'category_id é obrigatório'], 422);
        }

        $products = Product::query()
            ->where('category_id', $categoryId)
            ->orderBy('name')
            ->get(['id', 'name', 'ean', 'category_id']);

        return response()->json(['data' => $products]);
    }

    /**
     * Move um ou mais produtos para outra categoria.
     */
    public function moveProducts(MercadologicoProductsMoveRequest $request): RedirectResponse
    {
        $productIds = $request->validated('product_ids');
        $categoryId = $request->validated('category_id');

        Product::query()
            ->whereIn('id', $productIds)
            ->update(['category_id' => $categoryId]);

        $count = count($productIds);
        $message = $count === 1
            ? '1 produto movido com sucesso.'
            : "{$count} produtos movidos com sucesso.";

        return $this->redirectToMercadologicoIndex($request, $message);
    }
}
