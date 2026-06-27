<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor;

use App\Enums\ExecutionEvidenceType;
use App\Models\WorkflowExecutionDivergence;
use App\Models\WorkflowExecutionEvidence;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Models\Gondola;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Retorna o retorno da loja (divergências + evidências) de um produto,
 * agregando todas as gôndolas/execuções do planograma — consumido pela aba
 * "Execução" do painel de detalhes do produto no editor.
 */
class ProductExecutionController extends Controller
{
    /**
     * Lista as divergências e as evidências do tipo Produto registradas para o
     * produto nas execuções das gôndolas do planograma da gôndola informada.
     */
    public function feedback(Request $request, string $productId): JsonResponse
    {
        $gondolaId = $request->query('gondola_id');
        $planogramId = $gondolaId !== null
            ? Gondola::query()->whereKey($gondolaId)->value('planogram_id')
            : null;

        if ($planogramId === null) {
            return response()->json(['divergences' => [], 'evidences' => []]);
        }

        $inPlanogram = fn (Builder $query) => $query->whereHas(
            'execution.gondola',
            fn (Builder $gondolaQuery) => $gondolaQuery->where('planogram_id', $planogramId)
        );

        $divergences = WorkflowExecutionDivergence::query()
            ->where('product_id', $productId)
            ->where($inPlanogram)
            ->with(['execution.gondola:id,name,planogram_id'])
            ->latest()
            ->get();

        $evidences = WorkflowExecutionEvidence::query()
            ->where('product_id', $productId)
            ->where('type', ExecutionEvidenceType::Product->value)
            ->where($inPlanogram)
            ->with(['execution.gondola:id,name,planogram_id'])
            ->latest()
            ->get();

        return response()->json([
            'divergences' => $divergences->map(fn (WorkflowExecutionDivergence $divergence): array => [
                'id' => $divergence->id,
                'type' => $divergence->type?->value,
                'module_label' => $divergence->module_label,
                'shelf_label' => $divergence->shelf_label,
                'position_label' => $divergence->position_label,
                'status' => $divergence->status?->value,
                'notes' => $divergence->notes,
                'gondola_name' => $divergence->execution?->gondola?->name,
                'created_at' => $divergence->created_at?->toIso8601String(),
            ])->values(),
            'evidences' => $evidences->map(fn (WorkflowExecutionEvidence $evidence): array => [
                'id' => $evidence->id,
                'file_url' => $evidence->file_path !== null
                    ? Storage::disk('public')->url($evidence->file_path)
                    : null,
                'notes' => $evidence->notes,
                'gondola_name' => $evidence->execution?->gondola?->name,
                'created_at' => $evidence->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }
}
