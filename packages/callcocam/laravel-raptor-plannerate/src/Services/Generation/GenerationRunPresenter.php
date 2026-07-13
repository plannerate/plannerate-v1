<?php

namespace Callcocam\LaravelRaptorPlannerate\Services\Generation;

use Callcocam\LaravelRaptorPlannerate\Models\PlanogramGenerationRun;

/**
 * Serializa uma execução de geração (PlanogramGenerationRun) para a UI.
 *
 * Fonte única do formato consumido tanto pela API de histórico (JSON, polling do
 * editor) quanto pela página de relatório (props do Inertia) — antes o formato
 * vivia privado no PlanogramGenerationRunController e não podia ser reusado.
 */
final class GenerationRunPresenter
{
    /**
     * Resumo para listagem/polling — sem os JSONs pesados dos relatórios.
     *
     * @return array<string, mixed>
     */
    public function summary(PlanogramGenerationRun $run): array
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
    public function detail(PlanogramGenerationRun $run): array
    {
        return array_merge($this->summary($run), [
            'config_snapshot' => $run->config_snapshot,
            'capacity_report' => $run->capacity_report,
            'validation_report' => $run->validation_report,
            'template_id' => $run->template_id,
            'synth_template_id' => $run->synth_template_id,
        ]);
    }
}
