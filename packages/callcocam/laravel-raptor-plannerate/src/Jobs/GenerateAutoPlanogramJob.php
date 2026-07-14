<?php

namespace Callcocam\LaravelRaptorPlannerate\Jobs;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Notifications\AppNotificationDispatcher;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\AutoGenerationRunner;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\AutoGenerateConfigDTO;
use Callcocam\LaravelRaptorPlannerate\Enums\GenerationRunStatus;
use Callcocam\LaravelRaptorPlannerate\Exceptions\GenerationCancelledException;
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
     * @param  string|null  $userId  ULID do usuário que solicitou (será notificado). Null quando
     *                               a geração vem do agendador, que não tem sessão — nesse caso
     *                               não há ninguém para notificar e notify() simplesmente sai.
     * @param  string  $tenantId  ULID do tenant corrente no momento do dispatch
     * @param  string  $runId  ULID do PlanogramGenerationRun criado pelo controller
     */
    public function __construct(
        public string $gondolaId,
        public string $planogramId,
        public array $config,
        public ?string $templateId,
        public ?string $userId,
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
        } catch (GenerationCancelledException $e) {
            /*
             * SÓ cancelamento de negócio (ex.: nenhum produto elegível) — não é erro técnico,
             * mas o usuário precisa saber por que a gôndola não foi gerada.
             *
             * Antes isto capturava `\RuntimeException`, e QueryException TAMBÉM é uma
             * RuntimeException: uma falha real de banco (a tabela ausente `product_analyses`)
             * era engolida e mostrada ao usuário como "geração cancelada", sem nunca ser
             * tratada como o defeito que era. Qualquer outra exceção agora volta a estourar e
             * cai no failed() — que é o lugar certo para falha técnica.
             */
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
     * O envio é SÍNCRONO (ver AppNotificationDispatcher): a AppNotification é
     * ShouldQueue + NotTenantAware — se fosse re-enfileirada, rodaria num job separado
     * SEM tenant restaurado e não gravaria na conexão do tenant.
     */
    private function notify(string $title, string $message, string $type): void
    {
        if ($this->userId === null) {
            return;
        }

        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        app(AppNotificationDispatcher::class)->send(
            $user,
            new AppNotification(
                title: $title,
                message: $message,
                type: $type,
                actionUrl: $this->runActionUrl(),
                tenantId: $this->tenantId,
            ),
            context: 'GenerateAutoPlanogramJob',
        );
    }

    /**
     * Link "Ver detalhes" da notificação: abre o editor da gôndola, que carrega o
     * relatório da última execução (ver PlanogramGenerationRunController::latest).
     *
     * Apesar do nome da rota ('editor/planograms/{record}/gondolas/editor'), o
     * segmento {record} é o ID DA GÔNDOLA, não do planograma — ver
     * EditorPlanogramController::findGondolaOrFail() e o mesmo padrão em
     * GondolaController::edit(), Gondola::getRouteGondolasAttribute() e nos demais
     * jobs de notificação. Usar o planogramId aqui faz o
     * AppGondola::find($id) devolver null e o controller abortar com 403.
     *
     * URL relativa montada à mão (e não via route()): a notificação pode ser gravada
     * num worker sem o host do tenant no request, e route() geraria o host errado —
     * ver memória "Arquitetura multi-tenant real".
     */
    private function runActionUrl(): string
    {
        return "/editor/planograms/{$this->gondolaId}/gondolas/editor?run={$this->runId}";
    }
}
