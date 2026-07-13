<?php

namespace Callcocam\LaravelRaptorPlannerate\Services\Generation;

use App\Models\Tenant;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\AutoGenerateConfigDTO;
use Callcocam\LaravelRaptorPlannerate\Enums\GenerationRunStatus;
use Callcocam\LaravelRaptorPlannerate\Jobs\GenerateAutoPlanogramJob;
use Callcocam\LaravelRaptorPlannerate\Models\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Planogram;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramGenerationRun;
use Illuminate\Support\Facades\Log;

/**
 * Registra a execução e despacha o job de geração do planograma.
 *
 * Fonte única dos DOIS caminhos que geram uma gôndola:
 *  - AutoPlanogramController (geração/regeração pelo editor)
 *  - GondolaController::store (criação da gôndola já em modo automático)
 *
 * Sem isso, o segundo caminho continuaria gerando sincronamente dentro do request —
 * exatamente o que a Fase 0 do plano (docs/gondola-precisao-automatica/) elimina.
 */
final class GenerationQueueDispatcher
{
    /**
     * Cria o PlanogramGenerationRun (status `queued`, com snapshot da configuração
     * para auditoria posterior) e enfileira o job. Devolve o run criado.
     */
    public function dispatch(
        Gondola $gondola,
        Planogram $planogram,
        AutoGenerateConfigDTO $config,
        ?string $templateId,
    ): PlanogramGenerationRun {
        $userId = (string) auth()->id();

        $run = PlanogramGenerationRun::create([
            'planogram_id' => $planogram->id,
            'gondola_id' => $gondola->id,
            'user_id' => $userId,
            'status' => GenerationRunStatus::Queued,
            'mode' => $templateId ? 'template' : 'automatic',
            'config_snapshot' => $config->toArray(),
            'template_id' => $templateId,
        ]);

        GenerateAutoPlanogramJob::dispatch(
            gondolaId: $gondola->id,
            planogramId: $planogram->id,
            config: $config->toArray(),
            templateId: $templateId,
            userId: $userId,
            tenantId: (string) Tenant::current()?->getKey(),
            runId: $run->id,
        );

        Log::info('Geração de planograma enfileirada', [
            'run_id' => $run->id,
            'gondola_id' => $gondola->id,
            'mode' => $run->mode,
        ]);

        return $run;
    }
}
