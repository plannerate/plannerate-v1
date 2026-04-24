<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\Plannerate\Editor;

use Callcocam\LaravelRaptorFlow\Models\FlowExecution;
use Callcocam\LaravelRaptorFlow\Services\FlowManager;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Concerns\HasWorkflowToggle;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Http\Requests\Tenant\Plannerate\Editor\StoreGondolaRequest;
use Callcocam\LaravelRaptorPlannerate\Http\Requests\Tenant\Plannerate\Editor\UpdateGondolaRequest;
use Callcocam\LaravelRaptorPlannerate\Jobs\ProcessProductImagesByEansJob;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Category;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\GondolaAnalysis;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Planogram;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Planogram as EditorPlanogram;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Section;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\User;
use Callcocam\LaravelRaptorPlannerate\Services\Plannerate\GondolaPayloadService;
use Callcocam\LaravelRaptorPlannerate\Services\Plannerate\GondolaService;
use Callcocam\LaravelRaptorPlannerate\Support\WorkflowMorphMap;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class GondolaController extends Controller
{
    use HasWorkflowToggle;

    public function edit($planogram, $record)
    {
        $gondola = $this->findGondolaOrFail($record);
        $gondola->load([
            'planogram.gondolas',
            'planogram.category',
            'sections.gondola:id,scale_factor',
            'sections.shelves.segments.layer.product',
        ]);
        // Até aqui vai bem rapido
        $availableUsers = $this->getAvailableUsers($gondola->tenant_id);
        $recordData = app(GondolaPayloadService::class)->buildEditorPayload($gondola, $this->isWorkflowEnabled());

        if ($this->isWorkflowEnabled() &&
            (! data_get($recordData, 'planogram.gondolas') || data_get($recordData, 'planogram.gondolas') === [])) {
            abort(403, 'Planograma sem gôndolas. Não existe nenhuma gôndola associada a esta etapa do planograma.');
        }

        // Carregar análises mais recentes
        $abcAnalysis = GondolaAnalysis::getLatestAbcAnalysis($gondola->id);
        $stockAnalysis = GondolaAnalysis::getLatestStockAnalysis($gondola->id);

        return Inertia::render('tenant/plannerates/gondolas/EditV3', [
            'record' => $recordData,
            'availableUsers' => $availableUsers,
            'aiModelOptions' => $this->getAiModelOptions(),
            'strategyOptions' => $this->getStrategyOptions(),
            'backRoute' => route('tenant.plannerates.index', ['record' => $gondola->planogram_id], false),
            'saveChangesRoute' => route('api.editor.gondolas.save-changes', ['gondola' => $gondola->id], false),
            'analysis' => [
                'abc' => $abcAnalysis?->toAbcFormattedArray(),
                'stock' => $stockAnalysis?->toStockFormattedArray(),
            ],
            'permissions' => [
                'can_create_gondola' => $this->canCreateGondola($gondola->planogram), // Pode ser ajustado para verificar permissões reais
                'can_update_gondola' => auth()->user()->can('tenant.gondolas.edit'),
                'can_remove_gondola' => auth()->user()->can('tenant.gondolas.delete'), // Exemplo: só pode remover se não tiver seções
                'can_autogenate_gondola' => auth()->user()->can('tenant.gondolas.autogenerate'), // Permissão para autogerar gôndola
                'can_autogenate_gondola_ia' => auth()->user()->can('tenant.gondolas.autogenerate.ia'), // Permissão para autogerar gôndola IA
            ],
        ]);
    }

    public function show($planogram, $record)
    {
        $gondola = $this->findGondolaOrFail($record);
        $gondola->load([
            'planogram.gondolas',
            'planogram.category',
            'sections.gondola:id,scale_factor',
            'sections.shelves.segments.layer.product',
        ]);

        $recordData = app(GondolaPayloadService::class)->buildEditorPayload($gondola, $this->isWorkflowEnabled());

        // Carregar análises mais recentes
        $abcAnalysis = GondolaAnalysis::getLatestAbcAnalysis($gondola->id);
        $stockAnalysis = GondolaAnalysis::getLatestStockAnalysis($gondola->id);

        return Inertia::render('tenant/plannerates/gondolas/EditV3', [
            'record' => $recordData,
            'aiModelOptions' => $this->getAiModelOptions(),
            'strategyOptions' => $this->getStrategyOptions(),
            'backRoute' => route('tenant.plannerates.index', ['record' => $gondola->planogram_id], false),
            'saveChangesRoute' => route('api.editor.gondolas.save-changes', ['gondola' => $gondola->id], false),
            'analysis' => [
                'abc' => $abcAnalysis?->toAbcFormattedArray(),
                'stock' => $stockAnalysis?->toStockFormattedArray(),
            ],
            'permissions' => [
                'can_create_gondola' => $this->canCreateGondola($gondola->planogram), // Pode ser ajustado para verificar permissões reais
                'can_update_gondola' => true,
            ],
        ]);
    }

    public function store(StoreGondolaRequest $request, $planogram)
    {
        $planogramModel = Planogram::findOrFail($planogram);
        $shouldAutoStartWorkflow = $request->boolean('autoStartWorkflow');

        // Cria a gôndola sempre
        $gondola = app(GondolaService::class)->createGondolaWithStructure($planogramModel, $request->validated(), [
            'auto_start_workflow' => false, // Não auto-inicia aqui
        ]);

        if ($shouldAutoStartWorkflow && $this->isWorkflowEnabled()) {
            $flowExecution = FlowExecution::query()
                ->whereIn('workable_type', WorkflowMorphMap::gondolaWorkflowTypes())
                ->where('workable_id', $gondola->id)
                ->first();

            if ($flowExecution) {
                try {
                    app(FlowManager::class)->startPendingExecution(
                        $flowExecution,
                        (string) $request->user()->id
                    );

                    return redirect()->back()->with('success', 'Gôndola criada e workflow iniciado com sucesso!');
                } catch (ValidationException $e) {
                    return redirect()->back()->with('info', 'Gôndola criada! O workflow pode ser iniciado posteriormente no kanban.');
                }
            }
        }

        return redirect()->back()->with('success', 'Gôndola criada com sucesso!');
    }

    public function update($gondola, UpdateGondolaRequest $request)
    {
        $gondolaModel = Gondola::findOrFail($gondola);

        $gondolaModel->update([
            'name' => $request->gondolaName,
            'location' => $request->location,
            'side' => $request->side,
            'scale_factor' => $request->scaleFactor,
            'flow' => $request->flow,
            'status' => $request->status,
        ]);

        return redirect()->back()->with('success', 'Gôndola atualizada com sucesso!');
    }

    public function destroy($gondola)
    {
        $gondolaModel = Gondola::findOrFail($gondola);
        $planogramId = $gondolaModel->planogram_id;

        // Soft delete the gondola (cascade will handle sections, shelves, etc.)
        $gondolaModel->delete();

        if ($this->isWorkflowEnabled()) {
            FlowExecution::query()
                ->whereIn('workable_type', WorkflowMorphMap::gondolaWorkflowTypes())
                ->where('workable_id', $gondolaModel->id)
                ->forceDelete();
        }

        // Check if there are other gondolas in the planogram
        $remainingGondola = Gondola::where('planogram_id', $planogramId)->first();

        // If no more gondolas exist, redirect to planogram list
        if (! $remainingGondola) {
            return redirect()->route('tenant.planograms.index')
                ->with('success', 'Gôndola removida com sucesso! O planograma não possui mais gôndolas.');
        }

        // Otherwise, redirect to the first remaining gondola
        return redirect()->route('tenant.plannerates.editor.gondolas.edit', [
            'planogram' => $planogramId,
            'record' => $remainingGondola->id,
        ])->with('success', 'Gôndola removida com sucesso!');
    }

    /**
     * Retorna as seções de uma gôndola (apenas não deletadas)
     */
    public function sections($gondola)
    {
        $gondolaModel = Gondola::find($gondola);

        if (! $gondolaModel) {
            return response()->json(['error' => 'Gôndola não encontrada'], 404);
        }

        $sections = Section::query()
            ->where('gondola_id', $gondola)
            ->whereNull('deleted_at')
            ->orderBy('ordering', 'asc')
            ->get(['id', 'name', 'code', 'gondola_id', 'ordering']);

        return response()->json(['data' => $sections]);
    }

    /**
     * Helper Methods
     */
    protected function findGondolaOrFail(string $id): Gondola
    {
        $gondola = Gondola::find($id);

        if (! $gondola) {
            abort(403);
        }

        return $gondola;
    }

    protected function getAvailableUsers(string $tenantId): array
    {
        return Cache::remember("tenant_{$tenantId}_users_v2", now()->addMinutes(30), function () use ($tenantId) {
            return User::select('id', 'name')
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get()
                ->map(static fn ($user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                ])
                ->values()
                ->all();
        });
    }

    public function products($planogram, $record)
    {
        $gondola = Gondola::find($record);
        if (! $gondola) {
            return response()->json(['error' => 'Gondola not found'], 404);
        }

        // Carregar planogram uma única vez antes de qualquer acesso
        $gondola->loadMissing(['planogram']);

        // Parâmetros de filtro e paginação
        $page = request()->get('page', 1);
        $perPage = request()->get('per_page', 15);
        $search = request()->get('search', '');
        $showUsed = request()->boolean('show_used', false);
        $withDimensions = request()->boolean('with_dimensions', true);
        $categoryId = request()->get('category', $gondola->planogram->category_id);

        // Cache key único incluindo filtros
        // $cacheKey = sprintf(
        //     'products_planogram_%s_category_%s_client_%s_page_%s_search_%s_used_%s_dims_%s',
        //     $gondola->planogram_id,
        //     $categoryId ?? 'null',
        //     $gondola->planogram->client_id ?? 'null',
        //     $page,
        //     md5($search),
        //     $showUsed ? 'true' : 'false',
        //     $withDimensions
        // );

        // Obter IDs de categorias (hierarquia) — cacheado por categoria pois é estático
        $categoryIds = [];
        if ($categoryId) {
            $categoryIds = Cache::remember(
                "category_ids_{$categoryId}",
                now()->addHours(2),
                function () use ($categoryId) {
                    $category = Category::find($categoryId);
                    if (! $category) {
                        return [];
                    }

                    return array_unique(array_merge(
                        $category->getHierarchyIds(),
                        $category->getAllDescendantIds()
                    ));
                }
            );
        }

        // Obter IDs de produtos já usados

        $usedProductIds = DB::table('layers')
            ->join('segments', 'segments.id', '=', 'layers.segment_id')
            ->join('shelves', 'shelves.id', '=', 'segments.shelf_id')
            ->join('sections', 'sections.id', '=', 'shelves.section_id')
            ->join('gondolas', 'gondolas.id', '=', 'sections.gondola_id')
            ->where('gondolas.id', $gondola->id)
            ->whereNotNull('layers.product_id')
            ->whereNull('layers.deleted_at')
            ->distinct()
            ->pluck('layers.product_id')
            ->toArray();

        // Query de produtos
        $query = Product::query()
            ->with(['category']);

        // Filtro por cliente
        // Usa client_id diretamente (campo direto na tabela products)
        if ($clientId = $gondola->planogram->client_id) {
            $query->where('client_id', $clientId);
        }

        // Filtro por categoria
        if (! empty($categoryIds)) {
            $query->whereIn('category_id', $categoryIds);
        }

        // Filtro de busca
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('ean', 'like', "%{$search}%");
            });
        }

        // Filtro de produtos usados
        if (! $showUsed && ! empty($usedProductIds)) {
            $query->whereNotIn('id', $usedProductIds);
        }

        // Filtro por produtos com/sem dimensões (has_dimensions). Default: apenas com dimensão
        $query->where('has_dimensions', $withDimensions);

        // Paginação
        $paginator = $query->orderBy('name')->paginate($perPage, ['*'], 'page', $page);

        // Desabilita $appends automáticos — evita N×15 queries recursivas de full_path/formatted_*
        // Os valores necessários são extraídos manualmente no array_map abaixo
        foreach ($paginator->items() as $item) {
            $item->setAppends([]);
        }

        $result = [
            'data' => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'used_count' => count($usedProductIds),
        ];

        // Formatar produtos
        $products = array_map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'ean' => $product->ean,
                'image_url' => $product->getImageUrlAttribute(),
                'category_full_path' => $product->category->full_path ?? null,
                // Dimensões agora estão diretamente no produto (tabela dimensions foi removida)
                'width' => $product->width ?? 0,
                'height' => $product->height ?? 0,
                'depth' => $product->depth ?? 0,
                'weight' => $product->weight ?? null,
                'unit' => $product->unit ?? 'cm',
                'category_id' => $product->category_id,
                'category_name' => $product->category?->name,
                'status' => $product->status,
                'has_dimensions' => (bool) $product->has_dimensions,
                // 'sales' => $product->sales,
                'is_used' => false, // Já filtrado no backend se showUsed=false
            ];
        }, $result['data']);

        return response()->json([
            'products' => $products,
            'pagination' => [
                'current_page' => $result['current_page'],
                'last_page' => $result['last_page'],
                'per_page' => $result['per_page'],
                'total' => $result['total'],
            ],
            'used_count' => $result['used_count'],
        ]);
    }

    public function updateImages($gondola)
    {
        $gondolaModel = Gondola::with('planogram')->find($gondola);
        if (! $gondolaModel) {
            return redirect()->back()->with('error', 'Gôndola não encontrada.');
        }
        $planogram = $gondolaModel->planogram;
        if (! $planogram) {
            return redirect()->back()->with('error', 'Planograma da gôndola não encontrado.');
        }

        $productIds = Gondola::with('sections.shelves.segments.layer')
            ->find($gondola)
            ->sections
            ->flatMap(fn ($section) => $section->shelves)
            ->flatMap(fn ($shelf) => $shelf->segments)
            ->map(fn ($segment) => $segment->layer?->product_id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $eans = Product::query()
            ->whereIn('id', $productIds)
            ->pluck('ean')
            ->filter()
            ->values()
            ->toArray();

        $client = $planogram->client;
        $database = $client->database ?? config('database.connections.tenant.database');
        if (! $database) {
            return redirect()->back()->with('error', 'Database do cliente não configurado.');
        }

        ProcessProductImagesByEansJob::dispatch($eans, $database);

        return redirect()->back()->with(
            'success',
            'Atualização de imagens em segundo plano iniciada. '.count($eans).' produto(s) na fila.'
        );
    }

    /**
     * Retorna IDs de categorias do planograma (categoria + pais + descendentes) para filtrar produtos.
     *
     * @return array<int, string>
     */
    protected function getCategoryIdsForPlanogram(EditorPlanogram $planogram): array
    {
        $categoryId = $planogram->category_id;
        if (! $categoryId) {
            return [];
        }
        $category = Category::find($categoryId);
        if (! $category) {
            return [];
        }
        $hierarchyIds = $category->getHierarchyIds();
        $descendantIds = $category->getAllDescendantIds();

        return array_values(array_unique(array_merge($hierarchyIds, $descendantIds)));
    }

    public function getRouteGondolasAttribute()
    {
        if (! Route::has('tenant.plannerates.editor.gondolas.edit')) {
            return null;
        }

        return route('tenant.plannerates.editor.gondolas.edit', ['planogram' => $this->id]);
    }

    /**
     * @return array<int, array{value: string, label: string, description: string}>
     */
    protected function getAiModelOptions(): array
    {
        return [
            [
                'value' => 'gpt-4o-mini',
                'label' => 'GPT-4o Mini',
                'description' => '~$0.10 - Rápido e econômico',
            ],
            [
                'value' => 'gpt-4o',
                'label' => 'GPT-4o',
                'description' => '~$0.50 - Melhor qualidade',
            ],
            [
                'value' => 'claude-sonnet-4-6',
                'label' => 'Claude Sonnet 4.6',
                'description' => '~$0.30 - Anthropic qualidade',
            ],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string, description: string}>
     */
    protected function getStrategyOptions(): array
    {
        return [
            [
                'value' => 'abc',
                'label' => 'ABC',
                'description' => 'Prioriza produtos A (80% das vendas), depois B, depois C. Ideal para maximizar vendas.',
            ],
            [
                'value' => 'sales',
                'label' => 'Vendas',
                'description' => 'Ordena por volume total de vendas. Produtos mais vendidos têm prioridade.',
            ],
            [
                'value' => 'margin',
                'label' => 'Margem',
                'description' => 'Prioriza produtos com maior margem de contribuição. Foca em lucratividade.',
            ],
            [
                'value' => 'mix',
                'label' => 'Mix',
                'description' => 'Balanceado: 40% ABC + 40% Vendas + 20% Margem. Equilibra vendas e lucro.',
            ],
        ];
    }

    protected function canCreateGondola($model): bool
    {
        if (! $this->isWorkflowEnabled()) {
            return auth()->user()->can('tenant.gondolas.create');
        }

        if ($model && $model->flowConfigSteps()->exists()) {
            if ($model->flowConfigSteps()->count() > 0) {
                return auth()->user()->can('tenant.gondolas.create');
            }
        }

        return false;
    }
}
