<?php

namespace Callcocam\LaravelRaptorPlannerate\Jobs;

use App\Events\TenantNotificationBroadcast;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AppNotification;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\AutoGenerationRunner;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\AutoGenerateConfigDTO;
use Callcocam\LaravelRaptorPlannerate\Enums\GenerationRunStatus;
use Callcocam\LaravelRaptorPlannerate\Models\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Planogram;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramGenerationRun;
use Callcocam\LaravelRaptorPlannerate\Services\Generation\GenerationReportBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Jobs\TenantAware;

/**
 * Gera o planograma de uma gôndola em fila e notifica o usuário ao concluir.
 *
 * A geração era síncrona (dentro do request HTTP), o que limitava o quanto o motor
 * de posicionamento pode "pensar" para fechar a gôndola com precisão. Em fila, ela
 * pode demorar e até iterar até convergir (Fases 2/3 do plano em
 * docs/gondola-precisao-automatica/) sem travar a UI. O resultado é persistido em
 * PlanogramGenerationRun, ficando consultável depois — não só no flash do Inertia.
 *
 * TenantAware: o Spatie restaura o tenant corrente antes do handle(), então os models
 * tenant resolvem a conexão correta. O tenantId também é guardado como propriedade
 * para uso no failed() (onde o tenant pode não estar restaurado).
 */
class GenerateAutoPlanogramJob implements ShouldQueue, TenantAware
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Sem retry automático: a geração muta a gôndola (apaga e recria segmentos).
     * Um retry cego sobre uma falha parcial poderia duplicar trabalho — o usuário
     * reexecuta explicitamente pela UI se quiser.
     */
    public int $tries = 1;

    /**
     * Folga para gôndolas grandes (a fila `default` tem timeout 660s no Horizon).
     * As fases seguintes do plano tornam o placement mais caro (packer exato + loop
     * de convergência); se este teto apertar, criar supervisor dedicado.
     */
    public int $timeout = 600;

    /**
     * @param  string  $gondolaId  ULID da gôndola alvo
     * @param  string  $planogramId  ULID do planograma alvo
     * @param  array<string, mixed>  $config  AutoGenerateConfigDTO->toArray() (snapshot do formulário)
     * @param  string|null  $templateId  Template escolhido (null = modo automático)
     * @param  string  $userId  ULID do usuário que solicitou (será notificado)
     * @param  string  $tenantId  ULID do tenant corrente no momento do dispatch
     * @param  string  $runId  ULID do PlanogramGenerationRun criado pelo controller
     */
    public function __construct(
        public string $gondolaId,
        public string $planogramId,
        public array $config,
        public ?string $templateId,
        public string $userId,
        public string $tenantId,
        public string $runId,
    ) {
        $this->onQueue('default');
    }

    /**
     * Roda a geração, persiste o resultado no run e notifica o usuário.
     */
    public function handle(AutoGenerationRunner $runner, GenerationReportBuilder $reportBuilder): void
    {
        // Tenant já restaurado (TenantAware) → models tenant funcionam.
        $run = PlanogramGenerationRun::query()->find($this->runId);

        if (! $run) {
            Log::warning('GenerateAutoPlanogramJob: run não encontrado, abortando', [
                'run_id' => $this->runId,
                'tenant_id' => $this->tenantId,
            ]);

            return;
        }

        $run->markRunning();

        try {
            $gondola = Gondola::with(['sections.shelves'])->findOrFail($this->gondolaId);
            $planogram = Planogram::with(['category'])->findOrFail($this->planogramId);

            $result = $runner->run(
                $gondola,
                $planogram,
                AutoGenerateConfigDTO::fromArray($this->config),
                $this->templateId,
            );

            $capacityReport = $reportBuilder->buildCapacityReport($result, $this->templateId);
            $occupancy = $reportBuilder->buildOccupancyMetrics($result);
            $validationReport = $result->output->validationReport;

            $run->forceFill(array_merge($occupancy, [
                'status' => GenerationRunStatus::Completed,
                'finished_at' => now(),
                'duration_ms' => $run->elapsedMs(),
                'synth_template_id' => $result->synthTemplateId,
                'capacity_report' => $capacityReport,
                'validation_report' => $validationReport->toArray(),
            ]))->save();

            Log::info('GenerateAutoPlanogramJob: geração concluída', [
                'run_id' => $run->id,
                'gondola_id' => $this->gondolaId,
                'segments_placed' => $result->output->totalAllocated(),
                'occupancy_avg' => $occupancy['occupancy_avg'],
                'errors' => $validationReport->errorCount,
                'warnings' => $validationReport->warningCount,
            ]);

            $this->notify(
                title: __('plannerate.generation.notification.done_title'),
                message: __('plannerate.generation.notification.done_message', [
                    'count' => $result->output->totalAllocated(),
                ]),
                type: $validationReport->errorCount > 0 ? 'warning' : 'success',
            );
        } catch (\RuntimeException $e) {
            // Cancelamento de negócio (ex.: nenhum produto elegível) — não é erro técnico,
            // mas o usuário precisa saber por que a gôndola não foi gerada.
            $run->markFailed($e->getMessage());

            Log::info('GenerateAutoPlanogramJob: geração cancelada', [
                'run_id' => $run->id,
                'gondola_id' => $this->gondolaId,
                'reason' => $e->getMessage(),
            ]);

            $this->notify(
                title: __('plannerate.generation.notification.cancelled_title'),
                message: $e->getMessage(),
                type: 'warning',
            );
        }
    }

    /**
     * Falha técnica (exception não tratada / timeout). O tenant pode não estar
     * restaurado aqui, então re-seleciona antes de gravar o run e a notificação.
     */
    public function failed(\Throwable $e): void
    {
        Log::error('GenerateAutoPlanogramJob falhou', [
            'run_id' => $this->runId,
            'gondola_id' => $this->gondolaId,
            'tenant_id' => $this->tenantId,
            'error' => $e->getMessage(),
        ]);

        Tenant::query()->find($this->tenantId)?->makeCurrent();

        PlanogramGenerationRun::query()->find($this->runId)?->markFailed($e->getMessage());

        $this->notify(
            title: __('plannerate.generation.notification.failed_title'),
            message: __('plannerate.generation.notification.failed_message'),
            type: 'error',
        );
    }

    /**
     * Dispara a AppNotification (database + broadcast) para o usuário solicitante.
     *
     * Usa notifyNow() (envio SÍNCRONO) de propósito, pelo mesmo motivo documentado em
     * GenerateGondolaReportJob: a AppNotification é ShouldQueue + NotTenantAware — se
     * fosse re-enfileirada, rodaria num job separado SEM tenant restaurado e não
     * gravaria na conexão do tenant. O broadcast vai por evento próprio com dados
     * primitivos (não serializa o User da conexão tenant).
     */
    private function notify(string $title, string $message, string $type): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        $notification = new AppNotification(
            title: $title,
            message: $message,
            type: $type,
            actionUrl: $this->runActionUrl(),
            tenantId: $this->tenantId,
        );

        $user->notifyNow($notification, ['database']);

        try {
            TenantNotificationBroadcast::dispatch($this->userId, array_merge(
                $notification->toArray($user),
                [
                    'id' => $notification->id,
                    'type' => AppNotification::class,
                    'read_at' => null,
                ],
            ));
        } catch (\Throwable $e) {
            // A notificação já foi persistida (aparece ao recarregar); um problema de
            // broadcast (ex.: Reverb indisponível) não deve falhar a geração.
            Log::warning('GenerateAutoPlanogramJob: falha no broadcast (notificação já persistida)', [
                'user_id' => $this->userId,
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Link "Ver detalhes" da notificação: abre o editor do planograma, que carrega o
     * relatório da última execução (ver PlanogramGenerationRunController::latest).
     *
     * URL relativa montada à mão (e não via route()): a notificação pode ser gravada
     * num worker sem o host do tenant no request, e route() geraria o host errado —
     * ver memória "Arquitetura multi-tenant real".
     */
    private function runActionUrl(): string
    {
        return "/editor/planograms/{$this->planogramId}/gondolas/editor?run={$this->runId}";
    }
}
