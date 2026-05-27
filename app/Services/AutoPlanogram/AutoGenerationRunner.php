<?php

namespace App\Services\AutoPlanogram;

use App\Models\PlanogramSubtemplate;
use App\Models\PlanogramTemplateSlot;
use App\Models\Scopes\TenantScope;
use App\Models\ScoringWeights;
use App\Services\AutoPlanogram\DTO\AutoGenerateConfigDTO;
use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\DTO\PlanogramInput;
use App\Services\AutoPlanogram\Scoring\ScoringWeightsValue;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Planogram;

/**
 * Orquestra a geração de um planograma para uma gôndola: seleção/score de produtos,
 * montagem das settings e disparo do pipeline (AutoPlanogramService).
 *
 * Fonte única usada tanto pelo endpoint de geração (AutoPlanogramController) quanto pelo
 * fluxo de criação automática no stepper (GondolaController::store) — evita duplicar a
 * montagem do PlanogramInput e a vinculação ao template sintetizado.
 */
final class AutoGenerationRunner
{
    public function __construct(
        private readonly AutoPlanogramService $service,
        private readonly ProductSelectionService $productSelection,
    ) {}

    /**
     * Executa a geração. Atualiza template_id/generation_mode da gôndola e, no modo automático,
     * vincula a gôndola ao template sintetizado.
     *
     * @throws \RuntimeException quando não há produtos elegíveis (mensagem traduzida).
     */
    public function run(
        Gondola $gondola,
        Planogram $planogram,
        AutoGenerateConfigDTO $config,
        ?string $templateId,
    ): AutoGenerationResult {
        // Atualiza template_id e backfill de generation_mode para gôndolas antigas
        $gondola->forceFill([
            'template_id' => $templateId,
            'generation_mode' => $templateId ? 'template' : 'automatic',
        ])->save();

        // No modo template os slots definem as categorias — categoria do formulário é ignorada.
        // includeProductsWithoutSales=true para produtos sem histórico chegarem ao placer.
        $effectiveConfig = $templateId
            ? new AutoGenerateConfigDTO(
                strategy: $config->strategy,
                useExistingAnalysis: $config->useExistingAnalysis,
                startDate: $config->startDate,
                endDate: $config->endDate,
                minFacings: $config->minFacings,
                maxFacings: $config->maxFacings,
                groupBySubcategory: $config->groupBySubcategory,
                includeProductsWithoutSales: true,
                tableType: $config->tableType,
                categoryId: null,
            )
            : $config;

        // No modo template, restringe o pool às categorias que os slots do template cobrem,
        // em vez do departamento inteiro do planograma. Vazio → fallback para a categoria-base.
        $scopeCategoryIds = $templateId
            ? $this->resolveTemplateScopeCategoryIds($templateId, $gondola->sections->count())
            : null;

        $rankedProducts = $this->productSelection->selectAndRankProducts(
            $planogram,
            $effectiveConfig,
            requireDimensions: $templateId === null,
            scopeCategoryIds: $scopeCategoryIds,
        );

        if ($rankedProducts->isEmpty()) {
            throw new \RuntimeException(__('app.messages.no_products_found'));
        }

        $products = $rankedProducts->map(fn ($dto) => $dto->product);

        $abcClassMap = $rankedProducts
            ->filter(fn ($dto) => $dto->abcClass !== null)
            ->mapWithKeys(fn ($dto) => [$dto->product->id => $dto->abcClass])
            ->all();

        $targetStockMap = $rankedProducts
            ->filter(fn ($dto) => $dto->targetStock !== null && $dto->targetStock > 0)
            ->mapWithKeys(fn ($dto) => [$dto->product->id => (float) $dto->targetStock])
            ->all();

        $weightsModel = ScoringWeights::first();
        $weights = $weightsModel
            ? ScoringWeightsValue::fromModel($weightsModel)
            : ScoringWeightsValue::default();

        $tenantId = app('currentTenant')?->getKey();

        $settings = PlacementSettings::fromConfigDto($config)
            ->withExtras(tenantId: $tenantId, weights: $weights)
            ->withAbcMap($abcClassMap)
            ->withTargetStockMap($targetStockMap);

        if ($templateId) {
            $settings = $settings->withTemplate(
                templateId: $templateId,
                numModules: $gondola->sections->count(),
                planogramId: $planogram->id,
                products: $products,
            );
        }

        $input = new PlanogramInput(
            planogramId: $planogram->id,
            gondolaId: $gondola->id,
            tenantId: $tenantId ?? '',
            products: $products,
            sections: $gondola->sections,
            settings: $settings,
            planogramCategoryId: $planogram->category_id,
        );

        $output = $this->service->generate($input);

        // No modo automático: vincular gôndola ao template sintetizado e mudar para template-mode.
        // A origem auto é preservada no template.origin; o generation_mode deixa de ser 'automatic'.
        $synthTemplateId = null;
        if ($templateId === null && $output->subtemplateId !== null) {
            $synth = PlanogramSubtemplate::find($output->subtemplateId);
            if ($synth) {
                $synthTemplateId = $synth->template_id;
                $gondola->forceFill([
                    'template_id' => $synthTemplateId,
                    'generation_mode' => 'template',
                ])->save();
            }
        }

        return new AutoGenerationResult($output, $synthTemplateId, $products->count());
    }

    /**
     * Resolve os category_id distintos dos slots do subtemplate que será aplicado, para
     * restringir o pool de candidatos às categorias que o template realmente cobre.
     *
     * Espelha a seleção de subtemplate do TemplatePlacementEngine (maior num_modules <= seções)
     * para que o pool e o placement usem exatamente o mesmo escopo.
     *
     * @return list<string> Vazio se nenhum subtemplate/slot com categoria for encontrado
     *                      (o chamador então faz fallback para a categoria-base do planograma).
     */
    private function resolveTemplateScopeCategoryIds(string $templateId, int $numModules): array
    {
        $subtemplate = PlanogramSubtemplate::withoutGlobalScope(TenantScope::class)
            ->where('template_id', $templateId)
            ->where('num_modules', '<=', $numModules)
            ->where('is_active', true)
            ->orderByDesc('num_modules')
            ->first();

        if ($subtemplate === null) {
            return [];
        }

        return PlanogramTemplateSlot::withoutGlobalScope(TenantScope::class)
            ->where('subtemplate_id', $subtemplate->getKey())
            ->whereNotNull('category_id')
            ->pluck('category_id')
            ->unique()
            ->values()
            ->all();
    }
}
