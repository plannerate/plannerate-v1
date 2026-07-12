<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation;

use App\Models\Category;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\AutoGenerateConfigDTO;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement\ExposureRedistributeService;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement\VisualReorderService;
use Callcocam\LaravelRaptorPlannerate\Enums\PlacementFailureReason;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Http\Requests\Tenant\Plannerate\AutoGeneratePlanogramRequest;
use Callcocam\LaravelRaptorPlannerate\Models\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Layer;
use Callcocam\LaravelRaptorPlannerate\Models\Planogram;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramRejectedProduct;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramSubtemplate;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplateSlot;
use Callcocam\LaravelRaptorPlannerate\Services\Generation\GenerationQueueDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AutoPlanogramController extends Controller
{
    public function __construct(
        private readonly VisualReorderService $reorderService,
        private readonly ExposureRedistributeService $redistributeService,
        private readonly GenerationQueueDispatcher $queueDispatcher,
    ) {}

    /**
     * Enfileira a geração do planograma da gôndola.
     *
     * A geração deixou de ser síncrona: o motor de posicionamento pode demorar (e, a
     * partir da Fase 2/3 do plano, iterar até convergir) para fechar a gôndola com
     * precisão — o que não cabe num request HTTP. O resultado é persistido em
     * PlanogramGenerationRun e o usuário é notificado ao concluir.
     */
    public function generate(AutoGeneratePlanogramRequest $request, string $gondola): RedirectResponse
    {
        $gondolaModel = Gondola::findOrFail($gondola);

        $planogram = Planogram::find($gondolaModel->planogram_id);

        if (! $planogram) {
            return back()->with('warning', __('app.messages.planogram_not_found'));
        }

        $config = AutoGenerateConfigDTO::fromArray($request->validated());
        $templateId = $request->input('template_id');

        return $this->queueGeneration($gondolaModel, $planogram, $config, $templateId);
    }

    public function rejectedProducts(Request $request, string $gondola): JsonResponse
    {
        $gondolaModel = Gondola::findOrFail($gondola);

        $rejected = PlanogramRejectedProduct::with('product')
            ->where('gondola_id', $gondola)
            ->where('planogram_id', $gondolaModel->planogram_id)
            ->orderBy('category_name')
            ->orderBy('shelf_order')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'product_id' => $r->product_id,
                'product_name' => $r->product_name,
                'ean' => $r->ean,
                'image_url' => $r->product?->image_url ?: $r->image_url,
                'product_width' => $r->product_width,
                'product_height' => $r->product_height,
                'rejection_reason' => $r->rejection_reason->value,
                'rejection_reason_label' => $r->rejection_reason->label(),
                'slot_id' => $r->slot_id,
                'category_name' => $r->category_name,
                'category_id' => $r->category_id,
                'module_number' => $r->module_number,
                'shelf_order' => $r->shelf_order,
                'rejected_shelf_orders' => $r->rejected_shelf_orders ?? [],
            ]);

        return response()->json(['data' => $rejected]);
    }

    public function templateGroupings(Request $request, string $gondola): JsonResponse
    {

        $gondolaModel = Gondola::query()
            ->withCount('sections')
            ->findOrFail($gondola);

        if (! is_string($gondolaModel->template_id) || trim($gondolaModel->template_id) === '') {
            return response()->json([
                'data' => [],
                'meta' => [
                    'template_id' => null,
                    'subtemplate_id' => null,
                ],
            ]);
        }

        $numModules = max((int) $gondolaModel->sections_count, (int) ($gondolaModel->num_modulos ?? 0));

        $subtemplate = PlanogramSubtemplate::query()
            ->where('template_id', $gondolaModel->template_id)
            ->where('num_modules', '<=', $numModules)
            ->where('is_active', true)
            ->orderByDesc('num_modules')
            ->first();

        if (! $subtemplate) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'template_id' => $gondolaModel->template_id,
                    'subtemplate_id' => null,
                ],
            ]);
        }

        $slots = PlanogramTemplateSlot::query()
            ->where('subtemplate_id', $subtemplate->id)
            ->whereNotNull('category_id')
            ->orderBy('module_number')
            ->orderBy('shelf_order')
            ->get(['category_id', 'module_number', 'shelf_order']);

        $categoryIds = $slots->pluck('category_id')->unique()->filter()->values()->all();

        $categories = $categoryIds !== []
            ? Category::withoutGlobalScopes()
                ->whereIn('id', $categoryIds)
                ->get(['id', 'name'])
                ->keyBy('id')
            : collect();

        $groupings = $slots
            ->groupBy('category_id')
            ->map(function ($items, $categoryId) use ($categories): array {
                $categoryName = (string) ($categories->get($categoryId)?->name ?? $categoryId);

                return [
                    'category_id' => (string) $categoryId,
                    'category_name' => $categoryName,
                    'slots_count' => $items->count(),
                    'modules' => $items->pluck('module_number')->unique()->sort()->values()->all(),
                    'shelves' => $items->pluck('shelf_order')->unique()->sort()->values()->all(),
                ];
            })
            ->sortBy('category_name')
            ->values()
            ->all();

        return response()->json([
            'data' => $groupings,
            'meta' => [
                'template_id' => $gondolaModel->template_id,
                'subtemplate_id' => $subtemplate->id,
            ],
        ]);
    }

    public function destroyRejectedProduct(Request $request, string $gondola, string $rejected): JsonResponse
    {
        PlanogramRejectedProduct::where('id', $rejected)
            ->where('gondola_id', $gondola)
            ->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Reordena segmentos já posicionados no slot usando o motor de critérios visuais.
     * Não altera produtos nem frentes — apenas ordering e position.
     */
    public function reorderVisual(Request $request, string $gondola): JsonResponse
    {
        $request->validate([
            'slot_id' => ['required', 'string'],
        ]);

        $slot = PlanogramTemplateSlot::findOrFail($request->slot_id);
        $gondolaModel = Gondola::with(['sections.shelves'])->findOrFail($gondola);

        $count = $this->reorderService->reorder($gondolaModel, $slot);

        return response()->json([
            'success' => true,
            'reordered' => $count,
            'level' => 'reorder',
        ]);
    }

    /**
     * Redistribui segmentos já posicionados no slot ao mudar exposição (brand/flavor).
     * Mantém {produto: frentes} — recalcula apenas posições físicas.
     */
    public function redistributeExposure(Request $request, string $gondola): JsonResponse
    {
        $request->validate([
            'slot_id' => ['required', 'string'],
        ]);

        $slot = PlanogramTemplateSlot::findOrFail($request->slot_id);
        $gondolaModel = Gondola::with(['sections.shelves'])->findOrFail($gondola);

        $count = $this->redistributeService->redistribute($gondolaModel, $slot);

        return response()->json([
            'success' => true,
            'redistributed' => $count,
            'level' => 'redistribute',
        ]);
    }

    /**
     * Reordena visualmente TODOS os slots da gôndola de uma vez.
     * Usa o motor de critérios visuais de cada slot; mesmos produtos/frentes antes e depois.
     */
    public function reorderGondola(string $gondola): JsonResponse
    {
        $gondolaModel = Gondola::with(['sections.shelves'])->findOrFail($gondola);
        $slots = $this->resolveGondolaSlots($gondolaModel);
        $total = 0;

        foreach ($slots as $slot) {
            $total += $this->reorderService->reorder($gondolaModel, $slot);
        }

        return response()->json([
            'success' => true,
            'reordered' => $total,
            'slots_processed' => $slots->count(),
            'level' => 'reorder',
        ]);
    }

    /**
     * Redistribui segmentos de TODOS os slots da gôndola de uma vez.
     * Mantém {produto: frentes} — recalcula apenas posições físicas por exposição.
     */
    public function redistributeGondola(string $gondola): JsonResponse
    {
        $gondolaModel = Gondola::with(['sections.shelves'])->findOrFail($gondola);
        $slots = $this->resolveGondolaSlots($gondolaModel);
        $total = 0;

        foreach ($slots as $slot) {
            $total += $this->redistributeService->redistribute($gondolaModel, $slot);
        }

        return response()->json([
            'success' => true,
            'redistributed' => $total,
            'slots_processed' => $slots->count(),
            'level' => 'redistribute',
        ]);
    }

    /**
     * Resolve todos os slots do subtemplate que corresponde ao número de seções da gôndola.
     *
     * @return Collection<int, PlanogramTemplateSlot>
     */
    private function resolveGondolaSlots(Gondola $gondola): Collection
    {
        if (! $gondola->template_id) {
            return collect();
        }

        $numModules = $gondola->sections->count();

        $subtemplate = PlanogramSubtemplate::query()
            ->where('template_id', $gondola->template_id)
            ->where('num_modules', '<=', $numModules)
            ->orderByDesc('num_modules')
            ->first();

        if (! $subtemplate) {
            return collect();
        }

        return PlanogramTemplateSlot::query()
            ->where('subtemplate_id', $subtemplate->id)
            ->get();
    }

    /**
     * Re-sintetiza o template automático da gôndola do zero, sem parâmetros de formulário.
     * Usa configuração padrão (strategy=abc, análise existente) e é idempotente via source_gondola_id.
     * Disponível somente enquanto template.origin === 'auto'.
     *
     * Também enfileirado — mesma justificativa de generate().
     */
    public function regenerateAuto(string $gondola): RedirectResponse
    {
        $gondolaModel = Gondola::findOrFail($gondola);
        $planogram = Planogram::find($gondolaModel->planogram_id);

        if (! $planogram) {
            return back()->with('warning', __('app.messages.planogram_not_found'));
        }

        // Força modo automático: o synthesizer reutiliza o mesmo template por source_gondola_id
        $config = new AutoGenerateConfigDTO(
            strategy: 'abc',
            useExistingAnalysis: true,
            startDate: null,
            endDate: null,
            minFacings: 1,
            maxFacings: 10,
            groupBySubcategory: true,
            includeProductsWithoutSales: true,
            tableType: 'monthly_summaries',
            categoryId: null,
        );

        return $this->queueGeneration($gondolaModel, $planogram, $config, templateId: null);
    }

    /**
     * Enfileira a geração e devolve `back()` imediatamente — o usuário é avisado pela
     * notificação quando terminar. O registro/dispatch fica no GenerationQueueDispatcher,
     * compartilhado com o GondolaController (criação de gôndola em modo automático).
     */
    private function queueGeneration(
        Gondola $gondola,
        Planogram $planogram,
        AutoGenerateConfigDTO $config,
        ?string $templateId,
    ): RedirectResponse {
        $run = $this->queueDispatcher->dispatch($gondola, $planogram, $config, $templateId);

        Inertia::flash('toast', [
            'type' => 'info',
            'message' => __('plannerate.generation.queued'),
        ]);

        Inertia::flash('generation_run_id', $run->id);

        return back();
    }

    public function swapProduct(Request $request, string $gondola): JsonResponse
    {
        $request->validate([
            'rejected_product_id' => ['required', 'string'],
            'layer_id' => ['required', 'string'],
        ]);

        $rejected = PlanogramRejectedProduct::where('id', $request->rejected_product_id)
            ->where('gondola_id', $gondola)
            ->firstOrFail();

        $layer = Layer::findOrFail($request->layer_id);

        $layer->getConnection()->transaction(function () use ($rejected, $layer, $gondola): void {
            $tenantId = app('currentTenant')?->getKey() ?? '';

            // Captura o produto que estava posicionado antes de trocar
            $displacedProductId = $layer->product_id;
            $displacedEan = $layer->ean;
            $displacedProduct = $layer->product;

            // Posiciona o produto rejeitado no layer
            $layer->product_id = $rejected->product_id;
            $layer->ean = $rejected->ean;
            $layer->save();

            // Remove o rejeitado que foi posicionado
            $rejected->delete();

            // Cria novo registro de rejeitado para o produto deslocado
            if ($displacedProductId) {
                PlanogramRejectedProduct::create([
                    'id' => (string) Str::ulid(),
                    'tenant_id' => $tenantId,
                    'planogram_id' => $rejected->planogram_id,
                    'gondola_id' => $gondola,
                    'product_id' => $displacedProductId,
                    'product_name' => $displacedProduct?->name,
                    'ean' => $displacedEan,
                    'image_url' => $displacedProduct?->image_url ?? null,
                    'product_width' => $displacedProduct?->width ?? null,
                    'product_height' => $displacedProduct?->height ?? null,
                    'rejection_reason' => PlacementFailureReason::NoHorizontalSpace->value,
                    'slot_id' => $rejected->slot_id,
                    'category_name' => $rejected->category_name,
                    'category_id' => $rejected->category_id,
                    'module_number' => $rejected->module_number,
                    'shelf_order' => $rejected->shelf_order,
                ]);
            }
        });

        return response()->json(['success' => true]);
    }
}
