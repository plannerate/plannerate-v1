<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor;

use App\Enums\WorkflowExecutionStatus;
use App\Jobs\ProcessEanReferenceImageJob;
use App\Models\EanReference;
use App\Models\Tenant;
use App\Models\WorkflowGondolaExecution;
use App\Models\WorkflowPlanogramStep;
use App\Support\Authorization\PermissionName;
use App\Support\Modules\ModuleSlug;
use App\Support\Modules\TenantModuleService;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\AutoGenerateConfigDTO;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Http\Requests\Tenant\Plannerate\Editor\StoreGondolaRequest;
use Callcocam\LaravelRaptorPlannerate\Http\Requests\Tenant\Plannerate\Editor\UpdateGondolaRequest;
use Callcocam\LaravelRaptorPlannerate\Models\Category;
use Callcocam\LaravelRaptorPlannerate\Models\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\GondolaAnalysis;
use Callcocam\LaravelRaptorPlannerate\Models\Layer;
use Callcocam\LaravelRaptorPlannerate\Models\Planogram;
use Callcocam\LaravelRaptorPlannerate\Models\Planogram as EditorPlanogram;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplate;
use Callcocam\LaravelRaptorPlannerate\Models\Product;
use Callcocam\LaravelRaptorPlannerate\Models\Section;
use Callcocam\LaravelRaptorPlannerate\Models\Segment;
use Callcocam\LaravelRaptorPlannerate\Models\Shelf;
use Callcocam\LaravelRaptorPlannerate\Models\User;
use Callcocam\LaravelRaptorPlannerate\Services\Editor\GondolaPayloadService;
use Callcocam\LaravelRaptorPlannerate\Services\Editor\GondolaService;
use Callcocam\LaravelRaptorPlannerate\Services\Generation\GenerationQueueDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class GondolaController extends Controller
{
    protected function getBackRoute(Gondola $gondola): string
    {
        return route('tenant.planograms.index', ['record' => $gondola->planogram_id], false);
    }

    protected function getSaveChangesRoute(Gondola $gondola): string
    {
        return route('api.editor.gondolas.save-changes', [
            'gondola' => $gondola->id,
        ], false);
    }

    public function edit(string $record)
    {
        $gondola = $this->findGondolaOrFail($record);
        $this->authorize('view', $gondola);

        $gondola->load([
            'planogram.gondolas:id,planogram_id,name,slug',
            'planogram.category',
            'sections.gondola:id,scale_factor',
            'sections.shelves.segments.layer.product:id,name,ean,codigo_erp,url,width,height,depth,weight,current_stock,brand,status,category_id,type,reference,color,flavor,fragrance,subbrand,packaging_type,packaging_content,measurement_unit,price',
            'sections.shelves.segments.layer.product.category:id,name,category_id',
            // Carrega a cadeia de pais (até 7 níveis) para que category_full_path
            // (via getFullHierarchy()) não faça queries extras por nível.
            'sections.shelves.segments.layer.product.category.parent.parent.parent.parent.parent.parent',
        ]);

        // Desabilita appends automáticos nos produtos, exceto category_full_path,
        // que é montado manualmente pelo GondolaPayloadService a partir da cadeia
        // de categorias já eager-loaded acima (sem custo extra de query).
        $gondola->sections->each(function ($section) {
            $section->shelves->each(function ($shelf) {
                $shelf->segments->each(function ($segment) {
                    $segment->layer?->product?->setAppends([]);
                });
            });
        });

        // Até aqui vai bem rapido
        $availableUsers = $this->getAvailableUsers($gondola->tenant_id);
        $recordData = app(GondolaPayloadService::class)->buildEditorPayload($gondola);

        // Carregar análises mais recentes
        $abcAnalysis = GondolaAnalysis::getLatestAbcAnalysis($gondola->id);
        $stockAnalysis = GondolaAnalysis::getLatestStockAnalysis($gondola->id);
        $paperAnalysis = GondolaAnalysis::getLatestPaperAnalysis($gondola->id);
        $bcgAnalysis = GondolaAnalysis::getLatestBcgAnalysis($gondola->id);

        return Inertia::render('tenant/editor/Plannerate', [
            'record' => $recordData,
            'availableUsers' => $availableUsers,
            'aiModelOptions' => $this->getAiModelOptions(),
            'strategyOptions' => $this->getStrategyOptions(),
            'planogramTemplates' => $this->getPlanogramTemplates(),
            'backRoute' => $this->getBackRoute($gondola),
            'saveChangesRoute' => $this->getSaveChangesRoute($gondola),
            'analysis' => [
                'abc' => $abcAnalysis?->toAbcFormattedArray(),
                'stock' => $stockAnalysis?->toStockFormattedArray(),
                'paper' => $paperAnalysis?->toPaperFormattedArray(),
                'bcg' => $bcgAnalysis?->toBcgFormattedArray(),
            ],
            'permissions' => [
                'can_create_gondola' => $this->canCreateGondola($gondola->planogram),
                'can_update_gondola' => auth()?->user()?->can(PermissionName::TENANT_GONDOLAS_UPDATE),
                'can_remove_gondola' => auth()?->user()?->can(PermissionName::TENANT_GONDOLAS_DELETE),
                'can_autogenate_gondola' => auth()?->user()?->can(PermissionName::TENANT_GONDOLAS_AUTOGENERATE),
                'can_autogenate_gondola_ia' => auth()?->user()?->can(PermissionName::TENANT_GONDOLAS_AUTOGENERATE_IA),
            ],
        ]);
    }

    public function store(StoreGondolaRequest $request, string $planogram)
    {
        $planogramModel = Planogram::findOrFail($planogram);

        $gondola = app(GondolaService::class)->createGondolaWithStructure($planogramModel, $request->validated());

        $tenant = Tenant::current();
        $kanbanActive = $tenant !== null && app(TenantModuleService::class)->tenantHasActiveModule($tenant, ModuleSlug::KANBAN);

        if ($kanbanActive) {
            $this->createWorkflowExecution($gondola, $request);
        }

        $mode = $request->input('mode', 'manual');
        $editorUrl = route('tenant.planograms.gondolas.editor', ['record' => $gondola->id], false);

        // Garante que o modo solicitado é permitido pelo contrato do tenant.
        // Sem o módulo correspondente, degrada para manual silenciosamente.
        if ($mode === 'automatic' && ($tenant === null || ! app(TenantModuleService::class)->tenantHasActiveModule($tenant, ModuleSlug::PLANOGRAM_AUTOTIC))) {
            $mode = 'manual';
        }

        if ($mode === 'template' && ($tenant === null || ! app(TenantModuleService::class)->tenantHasActiveModule($tenant, ModuleSlug::PLANOGRAM_TEMPLATE))) {
            $mode = 'manual';
        }

        // Modo automático: o motor dirige a estrutura — cria a gôndola e ENFILEIRA a
        // geração, redirecionando ao editor, que acompanha o progresso e se atualiza
        // ao concluir (a geração pode demorar; ver docs/gondola-precisao-automatica/).
        if ($mode === 'automatic') {
            $config = AutoGenerateConfigDTO::fromArray($request->validated());

            app(GenerationQueueDispatcher::class)->dispatch($gondola, $planogramModel, $config, templateId: null);

            return redirect($editorUrl)->with('info', __('plannerate.generation.queued'));
        }

        // Modo template: cria a estrutura e abre o modal de geração no editor.
        if ($mode === 'template') {
            return redirect($editorUrl)->with('auto_generate', true);
        }

        return redirect()->back()->with('success', 'Gôndola criada com sucesso!');
    }

    private function createWorkflowExecution(Gondola $gondola, StoreGondolaRequest $request): void
    {
        $firstStep = WorkflowPlanogramStep::query()
            ->where('planogram_id', $gondola->planogram_id)
            ->where('is_skipped', false)
            ->with('template:id,suggested_order')
            ->get()
            ->sortBy(fn (WorkflowPlanogramStep $step): int => $step->template?->suggested_order ?? PHP_INT_MAX)
            ->first();

        if ($firstStep === null) {
            return;
        }

        $userId = $request->user()?->getAuthIdentifier();
        $responsibleId = $request->boolean('assignToCurrentUser') || ! $request->filled('assignedUserId')
            ? $userId
            : $request->input('assignedUserId');

        $autoStart = $request->boolean('autoStartWorkflow');

        WorkflowGondolaExecution::query()->create([
            'tenant_id' => $gondola->tenant_id,
            'gondola_id' => $gondola->id,
            'workflow_planogram_step_id' => $firstStep->id,
            'status' => $autoStart ? WorkflowExecutionStatus::Active : WorkflowExecutionStatus::Pending,
            'user_id' => $userId,
            'current_responsible_id' => $responsibleId,
            'execution_started_by' => $autoStart ? $userId : null,
            'started_at' => $autoStart ? now() : null,
        ]);
    }

    public function update(string $gondola, UpdateGondolaRequest $request)
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

    public function destroy(string $gondola)
    {

        $gondolaModel = Gondola::findOrFail($gondola);

        $gondolaModel->delete();

        return redirect($this->getBackRoute($gondolaModel))
            ->with('success', 'Gôndola removida com sucesso!');
    }

    /**
     * Retorna as seções de uma gôndola (apenas não deletadas)
     */
    public function sections(string $gondola)
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
        return Cache::remember("tenant_{$tenantId}_users_v2", now()->addMinutes(30), function () {
            return User::select('id', 'name')
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

    public function products(Request $request, string $planogram, string $gondola)
    {
        $gondolaModel = Gondola::find($gondola);
        if (! $gondolaModel) {
            return response()->json(['error' => 'Gondola not found'], 404);
        }

        // Carregar planogram uma única vez antes de qualquer acesso
        $gondolaModel->loadMissing(['planogram']);

        // Parâmetros de filtro e paginação
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 15);
        $search = $request->input('search', '');
        $showUsed = $request->boolean('show_used', false);
        $withDimensions = $request->boolean('with_dimensions', true);
        $categoryId = $request->input('category', $gondolaModel->planogram->category_id);

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

        // Obter IDs de produtos já usados na gôndola.
        // Usa JOINs via segment -> shelf -> section para evitar depender de colunas
        // auxiliares inexistentes em layers.
        $gondolaId = $gondolaModel->id;
        $usedProductIds = Layer::query()
            ->join('segments', 'segments.id', '=', 'layers.segment_id')
            ->join('shelves', 'shelves.id', '=', 'segments.shelf_id')
            ->join('sections', 'sections.id', '=', 'shelves.section_id')
            ->where('sections.gondola_id', $gondolaId)
            ->whereNotNull('product_id')
            ->whereNull('layers.deleted_at')
            ->distinct()
            ->pluck('layers.product_id')
            ->toArray();

        // Query de produtos
        // Carrega a cadeia de pais (até 7 níveis) para que getFullHierarchy() não faça
        // queries extras por nível — elimina N×depth queries por página
        $query = Product::query()
            ->with(['category.parent.parent.parent.parent.parent.parent']);

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

        // Filtro de produtos usados via cadeia relacional (layers -> segments -> shelves -> sections)
        if (! $showUsed) {
            $query->whereNotExists(function ($sub) use ($gondolaId): void {
                $sub->select(DB::raw(1))
                    ->from('layers')
                    ->join('segments', 'segments.id', '=', 'layers.segment_id')
                    ->join('shelves', 'shelves.id', '=', 'segments.shelf_id')
                    ->join('sections', 'sections.id', '=', 'shelves.section_id')
                    ->whereColumn('layers.product_id', 'products.id')
                    ->where('sections.gondola_id', $gondolaId)
                    ->whereNull('layers.deleted_at');
            });
        }

        // Filtro por produtos com/sem dimensões (calculado de width/height/depth). Default: apenas com dimensão
        if ($withDimensions) {
            $query->where('width', '>', 0)->where('height', '>', 0)->where('depth', '>', 0);
        } else {
            $query->where(function ($q) {
                $q->where('width', '<=', 0)->orWhereNull('width')
                    ->orWhere('height', '<=', 0)->orWhereNull('height')
                    ->orWhere('depth', '<=', 0)->orWhereNull('depth');
            });
        }

        // Paginação
        $paginator = $query->orderBy('name')->paginate($perPage, ['*'], 'page', $page);

        // Desabilita $appends automáticos — evita queries recursivas de full_path/formatted_*
        // Os valores necessários são extraídos manualmente no array_map abaixo
        foreach ($paginator->items() as $item) {
            $item->setAppends([]);
            // Desabilita também os appends da categoria (mercadologico_cascading, hierarchy_path)
            $item->category?->setAppends([]);
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
                'codigo_erp' => $product->codigo_erp,
                'image_url' => $product->getImageUrlAttribute(),
                'category_full_path' => $product->category_full_path,
                // Dimensões agora estão diretamente no produto (tabela dimensions foi removida)
                'width' => $product->width ?? 0,
                'height' => $product->height ?? 0,
                'depth' => $product->depth ?? 0,
                'weight' => $product->weight ?? null,
                'unit' => $product->unit ?? 'cm',
                'category_id' => $product->category_id,
                'category_name' => $product->category?->name,
                'brand' => $product->brand,
                'status' => $product->status,
                'has_dimensions' => ($product->width > 0 && $product->height > 0 && $product->depth > 0),
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

    public function updateImages(string $gondola)
    {
        $gondolaModel = Gondola::find($gondola);
        if (! $gondolaModel) {
            return redirect()->back()->with('error', 'Gôndola não encontrada.');
        }

        $tenant = Tenant::current();
        if (! $tenant) {
            return redirect()->back()->with('error', 'Contexto de tenant não encontrado.');
        }

        if (! app(TenantModuleService::class)->tenantHasActiveModule($tenant, ModuleSlug::IMAGE_BANK)) {
            return redirect()->back()->with('error', 'Módulo de banco de imagens não habilitado.');
        }

        // Coleta os EANs de todos os produtos presentes nas prateleiras da gôndola
        $sectionIds = Section::query()
            ->where('gondola_id', $gondolaModel->id)
            ->pluck('id');

        $shelfIds = Shelf::query()
            ->whereIn('section_id', $sectionIds)
            ->pluck('id');

        $segmentIds = Segment::query()
            ->whereIn('shelf_id', $shelfIds)
            ->pluck('id');

        $productIds = Layer::query()
            ->whereIn('segment_id', $segmentIds)
            ->whereNotNull('product_id')
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('product_id')
            ->toArray();

        $rawEans = Product::query()
            ->whereIn('id', $productIds)
            ->pluck('ean')
            ->filter()
            ->values()
            ->toArray();

        // Normaliza e deduplica os EANs coletados
        $normalizedEans = collect($rawEans)
            ->filter(fn (mixed $ean): bool => is_string($ean) && trim($ean) !== '')
            ->map(fn (string $ean): string => EanReference::normalizeEan($ean))
            ->filter(fn (string $ean): bool => $ean !== '')
            ->unique()
            ->values()
            ->all();

        if (empty($normalizedEans)) {
            return redirect()->back()->with('warning', 'Nenhum produto com EAN encontrado na gôndola.');
        }

        $tenantId = (string) $tenant->id;

        // EANs com imagem já cacheada em ean_references → atualiza products.url diretamente
        $referencesWithImage = EanReference::query()
            ->whereIn('ean', $normalizedEans)
            ->whereNotNull('image_front_url')
            ->where('image_front_url', '!=', '')
            ->whereNull('deleted_at')
            ->get(['id', 'ean', 'image_front_url']);

        $eansWithImage = $referencesWithImage
            ->pluck('ean')
            ->map(fn (mixed $e): string => (string) $e)
            ->all();

        $eansNeedingDownload = array_values(array_diff($normalizedEans, $eansWithImage));

        $synced = 0;
        $queued = 0;

        // Sincroniza diretamente os produtos que já têm imagem cacheada
        foreach ($referencesWithImage as $reference) {
            $path = (string) $reference->image_front_url;
            $ean = (string) $reference->ean;

            $count = Product::query()
                ->where('ean', $ean)
                ->where(fn ($q) => $q->whereNull('url')->orWhere('url', '!=', $path))
                ->update(['url' => $path, 'updated_at' => now()]);

            $synced += $count;
        }

        // EANs sem imagem: garante registro em ean_references e despacha download em background
        foreach ($eansNeedingDownload as $ean) {
            /** @var EanReference $reference */
            $reference = EanReference::firstOrCreate(['ean' => $ean]);

            ProcessEanReferenceImageJob::dispatch(
                eanReferenceId: (string) $reference->id,
                force: false,
                tenantIds: [$tenantId],
            );
            $queued++;
        }

        $parts = [];

        if ($synced > 0) {
            $parts[] = "{$synced} produto(s) com imagem atualizada";
        }

        if ($queued > 0) {
            $parts[] = "{$queued} EAN(s) em download em segundo plano";
        }

        return redirect()->back()->with(
            'success',
            'Atualização de imagens iniciada: '.implode(', ', $parts ?: ['nenhuma alteração']).'.'
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
     * @return array<int, array{value: string, label: string, num_modules: int}>
     */
    protected function getPlanogramTemplates(): array
    {
        $tenantId = Tenant::current()?->getKey();

        if ($tenantId === null) {
            return [];
        }

        return PlanogramTemplate::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'department'])
            ->map(fn (PlanogramTemplate $t) => [
                'value' => $t->id,
                'label' => $t->name,
                'description' => $t->department,
            ])
            ->values()
            ->toArray();
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
        return auth()->user()->can(PermissionName::TENANT_GONDOLAS_CREATE);
    }
}
