<?php

namespace App\Http\Controllers;

use App\Models\ScoringWeights;
use App\Services\AutoPlanogram\AutoPlanogramService;
use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\DTO\PlanogramInput;
use App\Services\AutoPlanogram\Scoring\ScoringWeightsValue;
use Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\AutoGenerate\AutoGenerateConfigDTO;
use Callcocam\LaravelRaptorPlannerate\Http\Requests\Tenant\Plannerate\AutoGeneratePlanogramRequest;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Planogram;
use Callcocam\LaravelRaptorPlannerate\Services\Plannerate\AutoGenerate\ProductSelectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AutoPlanogramController extends Controller
{
    public function __construct(
        private readonly AutoPlanogramService $service,
        private readonly ProductSelectionService $productSelection,
    ) {}

    public function generate(AutoGeneratePlanogramRequest $request, string $subdomain, string $gondola): RedirectResponse
    {
        try {
            $config = AutoGenerateConfigDTO::fromArray($request->validated());

            $gondolaModel = Gondola::with(['sections.shelves'])->findOrFail($gondola);

            $planogram = Planogram::with(['category'])->find($gondolaModel->planogram_id);

            if (! $planogram) {
                return back()->with('warning', __('app.messages.planogram_not_found'));
            }

            $rankedProducts = $this->productSelection->selectAndRankProducts($planogram, $config);

            if ($rankedProducts->isEmpty()) {
                return back()->with('warning', __('app.messages.no_products_found'));
            }

            $products = $rankedProducts->map(fn ($dto) => $dto->product);

            $weightsModel = ScoringWeights::first();
            $weights = $weightsModel
                ? ScoringWeightsValue::fromModel($weightsModel)
                : ScoringWeightsValue::default();

            $tenantId = app('currentTenant')?->getKey();

            $settings = PlacementSettings::fromConfigDto($config)->withExtras(
                tenantId: $tenantId,
                weights: $weights,
            );

            $input = new PlanogramInput(
                planogramId: $planogram->id,
                gondolaId: $gondola,
                tenantId: $tenantId ?? '',
                products: $products,
                sections: $gondolaModel->sections,
                settings: $settings,
            );

            $output = $this->service->generate($input);

            $report = $output->validationReport;

            Inertia::flash('toast', [
                'type' => $report->errorCount > 0 ? 'warning' : 'success',
                'message' => trans_choice(
                    'app.messages.planogram_generated',
                    $output->totalAllocated(),
                    ['count' => $output->totalAllocated()]
                ),
            ]);

            Inertia::flash('validation_report', $report->toArray());

            Log::info('AutoPlanogramController: geração concluída', [
                'gondola_id' => $gondola,
                'segments_placed' => $output->totalAllocated(),
                'errors' => $report->errorCount,
                'warnings' => $report->warningCount,
            ]);

            return back();

        } catch (\RuntimeException $e) {
            Log::info('AutoPlanogramController: geração cancelada', [
                'gondola_id' => $gondola,
                'reason' => $e->getMessage(),
            ]);

            return back()->with('warning', $e->getMessage());

        } catch (\Exception $e) {
            Log::error('AutoPlanogramController: erro técnico', [
                'gondola_id' => $gondola,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', $e->getMessage());
        }
    }
}
