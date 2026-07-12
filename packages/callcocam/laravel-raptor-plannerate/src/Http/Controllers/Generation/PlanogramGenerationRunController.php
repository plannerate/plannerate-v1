<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation;

use Callcocam\LaravelRaptorPlannerate\Enums\GenerationRunStatus;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramGenerationRun;
use Illuminate\Http\JsonResponse;

/**
 * Consulta do histórico de execuções da geração de planograma.
 *
 * Existe porque o resultado da geração deixou de ser síncrono: o job persiste o
 * relatório no PlanogramGenerationRun e a UI o busca aqui — tanto para exibir logo
 * após a conclusão quanto para reabrir execuções antigas e comparar ocupação.
 */
class PlanogramGenerationRunController extends Controller
{
    /**
     * Histórico de execuções da gôndola (mais recentes primeiro), sem os relatórios
     * completos — só o resumo que a listagem precisa.
     */
    public function index(string $gondola): JsonResponse
    {
        $runs = PlanogramGenerationRun::query()
            ->where('gondola_id', $gondola)
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (PlanogramGenerationRun $run) => $this->toSummary($run));

        return response()->json(['data' => $runs]);
    }

    /**
     * Detalhe de uma execução — inclui os relatórios completos (capacity/validation).
     */
    public function show(string $gondola, string $run): JsonResponse
    {
        $model = PlanogramGenerationRun::query()
            ->where('gondola_id', $gondola)
            ->findOrFail($run);

        return response()->json(['data' => $this->toDetail($model)]);
    }

    /**
     * Última execução da gôndola — o editor chama isto ao abrir para hidratar o
     * banner de capacidade/validação, que antes vinha do flash do Inertia.
     *
     * Devolve `data: null` (200) quando a gôndola nunca foi gerada.
     */
    public function latest(string $gondola): JsonResponse
    {
        $run = PlanogramGenerationRun::query()
            ->where('gondola_id', $gondola)
            ->latest()
            ->first();

        return response()->json([
            'data' => $run ? $this->toDetail($run) : null,
        ]);
    }

    /**
     * Resumo para listagem/polling — sem os JSONs pesados.
     *
     * @return array<string, mixed>
     */
    private function toSummary(PlanogramGenerationRun $run): array
    {
        return [
            'id' => $run->id,
            'status' => $run->status->value,
            'status_label' => $run->status->label(),
            'is_pending' => $run->status->isPending(),
            'mode' => $run->mode,
            'occupancy_avg' => $run->occupancy_avg,
            'occupancy_min' => $run->occupancy_min,
            'occupancy_max' => $run->occupancy_max,
            'iterations_run' => $run->iterations_run,
            'converged' => $run->converged,
            'duration_ms' => $run->duration_ms,
            'error_message' => $run->error_message,
            'created_at' => $run->created_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
        ];
    }

    /**
     * Detalhe completo — resumo + relatórios + snapshot da configuração usada.
     *
     * @return array<string, mixed>
     */
    private function toDetail(PlanogramGenerationRun $run): array
    {
        return array_merge($this->toSummary($run), [
            'config_snapshot' => $run->config_snapshot,
            'capacity_report' => $run->capacity_report,
            'validation_report' => $run->validation_report,
            'template_id' => $run->template_id,
            'synth_template_id' => $run->synth_template_id,
        ]);
    }

    /**
     * Sinaliza se a gôndola tem geração em andamento — usado pela UI para exibir
     * o estado "gerando..." ao reabrir o editor antes do job terminar.
     */
    public function pending(string $gondola): JsonResponse
    {
        $pending = PlanogramGenerationRun::query()
            ->where('gondola_id', $gondola)
            ->whereIn('status', [GenerationRunStatus::Queued, GenerationRunStatus::Running])
            ->latest()
            ->first();

        return response()->json([
            'data' => $pending ? $this->toSummary($pending) : null,
        ]);
    }
}
