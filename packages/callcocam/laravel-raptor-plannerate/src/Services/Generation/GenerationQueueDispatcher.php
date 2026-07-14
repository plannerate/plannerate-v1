<?php

namespace Callcocam\LaravelRaptorPlannerate\Services\Generation;

use App\Models\Tenant;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\AutoGenerateConfigDTO;
use Callcocam\LaravelRaptorPlannerate\Enums\GenerationRunKind;
use Callcocam\LaravelRaptorPlannerate\Enums\GenerationRunStatus;
use Callcocam\LaravelRaptorPlannerate\Enums\GenerationRunTrigger;
use Callcocam\LaravelRaptorPlannerate\Enums\ProposalStatus;
use Callcocam\LaravelRaptorPlannerate\Jobs\GenerateAutoPlanogramJob;
use Callcocam\LaravelRaptorPlannerate\Models\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Planogram;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramGenerationRun;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramReoptimizationProposal;
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
     *
     * @param  ?string  $userId  Quem receberá a notificação. Default: o usuário autenticado.
     *                           Explícito porque o agendador roda no console, sem sessão —
     *                           `auth()->id()` ali devolve null e gravaria user_id vazio.
     * @param  GenerationRunTrigger  $trigger  Manual (pedido por alguém) ou agendada.
     */
    public function dispatch(
        Gondola $gondola,
        Planogram $planogram,
        AutoGenerateConfigDTO $config,
        ?string $templateId,
        ?string $userId = null,
        GenerationRunTrigger $trigger = GenerationRunTrigger::Manual,
    ): PlanogramGenerationRun {
        $userId ??= auth()->id();
        $userId = $userId !== null ? (string) $userId : null;

        // Uma geração nova reescreve a gôndola inteira: qualquer proposta de reotimização pendente
        // foi calculada contra um layout que está prestes a deixar de existir. Marcá-las agora é
        // mais honesto do que deixá-las na fila para serem recusadas por staleness depois — o
        // usuário não perde tempo revisando um diff que já nasceu morto.
        $superseded = PlanogramReoptimizationProposal::query()
            ->where('gondola_id', $gondola->id)
            ->pending()
            ->update(['status' => ProposalStatus::Superseded]);

        if ($superseded > 0) {
            Log::info('Propostas de reotimização invalidadas por nova geração', [
                'gondola_id' => $gondola->id,
                'count' => $superseded,
            ]);
        }

        $run = PlanogramGenerationRun::create([
            'planogram_id' => $planogram->id,
            'gondola_id' => $gondola->id,
            'user_id' => $userId,
            'status' => GenerationRunStatus::Queued,
            'mode' => $templateId ? 'template' : 'automatic',
            'kind' => GenerationRunKind::Apply,
            'trigger' => $trigger,
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
            'trigger' => $trigger->value,
        ]);

        return $run;
    }
}
