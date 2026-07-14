<?php

namespace Callcocam\LaravelRaptorPlannerate\Jobs;

use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Notifications\AppNotificationDispatcher;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\AutoGenerationRunner;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\AutoGenerateConfigDTO;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacedSegment;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement\RejectedProductsWriter;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Snapshot\GondolaLayoutReader;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Snapshot\LayoutHasher;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Snapshot\LayoutSnapshotSerializer;
use Callcocam\LaravelRaptorPlannerate\Enums\GenerationRunStatus;
use Callcocam\LaravelRaptorPlannerate\Enums\GenerationRunTrigger;
use Callcocam\LaravelRaptorPlannerate\Enums\ProposalStatus;
use Callcocam\LaravelRaptorPlannerate\Exceptions\GenerationCancelledException;
use Callcocam\LaravelRaptorPlannerate\Models\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Planogram;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramGenerationRun;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramRejectedProduct;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramReoptimizationProposal;
use Callcocam\LaravelRaptorPlannerate\Services\Generation\GenerationReportBuilder;
use Callcocam\LaravelRaptorPlannerate\Services\Reoptimization\GondolaLayoutDiffService;
use Callcocam\LaravelRaptorPlannerate\Services\Reoptimization\LayoutDiffContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Jobs\TenantAware;

/**
 * Reprocessa uma gôndola contra os dados de venda atuais SEM tocar nela, e grava o resultado
 * como uma proposta para o usuário revisar.
 *
 * A diferença para o GenerateAutoPlanogramJob é uma só, mas é toda a razão de existir desta
 * feature: aqui o pipeline roda em `dryRun`. A geração de planograma é destrutiva (apaga e recria
 * todos os segments da gôndola); reprocessar automaticamente e gravar direto destruiria o ajuste
 * manual do usuário toda semana, sem aviso. Então o resultado vira uma proposta com diff, e o
 * layout calculado é guardado por inteiro — aprovar aplica AQUELE snapshot, não recalcula.
 */
class GenerateReoptimizationProposalJob implements ShouldQueue, TenantAware
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Sem retry: a análise é barata de repetir na próxima rodada e um retry cego só empilharia runs. */
    public int $tries = 1;

    public int $timeout = 600;

    /**
     * Fila própria.
     *
     * Na `default`, uma madrugada com 30 gôndolas reprocessando ocuparia os workers e faria a
     * geração pedida por um usuário esperar atrás de trabalho de fundo que ninguém aguarda.
     */
    public const QUEUE = 'reoptimization';

    public function __construct(
        public string $gondolaId,
        public string $planogramId,
        public array $config,
        public string $templateId,
        public ?string $userId,
        public string $tenantId,
        public string $runId,
        public GenerationRunTrigger $trigger = GenerationRunTrigger::Scheduled,
    ) {
        $this->onQueue(self::QUEUE);
    }

    public function handle(
        AutoGenerationRunner $runner,
        GenerationReportBuilder $reportBuilder,
        GondolaLayoutReader $layoutReader,
        LayoutSnapshotSerializer $serializer,
        LayoutHasher $hasher,
        GondolaLayoutDiffService $diffService,
        RejectedProductsWriter $rejectedWriter,
    ): void {
        $run = PlanogramGenerationRun::query()->find($this->runId);

        if (! $run) {
            Log::warning('GenerateReoptimizationProposalJob: run não encontrado, abortando', [
                'run_id' => $this->runId,
                'tenant_id' => $this->tenantId,
            ]);

            return;
        }

        $run->markRunning();

        try {
            $gondola = Gondola::with(['sections.shelves'])->findOrFail($this->gondolaId);
            $planogram = Planogram::with(['category'])->findOrFail($this->planogramId);

            // Baseline ANTES de rodar o motor: é o layout que o diff descreve e o hash protege.
            $baseline = $layoutReader->read($gondola, excludeLockedShelves: true);
            $baselineHash = $hasher->hash($baseline);

            $rejectedBefore = PlanogramRejectedProduct::query()
                ->where('gondola_id', $this->gondolaId)
                ->get()
                ->map(fn ($row): array => ['product_id' => (string) $row->product_id, 'rejection_reason' => $row->rejection_reason])
                ->all();

            $result = $runner->run(
                $gondola,
                $planogram,
                AutoGenerateConfigDTO::fromArray($this->config),
                $this->templateId,
                dryRun: true,
            );

            $proposed = $result->output->placedSegments;
            $rejectedAfter = $rejectedWriter->rows(
                $this->planogramId,
                $this->gondolaId,
                $this->tenantId,
                $result->output,
            );

            $diff = $diffService->diff(
                $baseline,
                $proposed,
                $rejectedBefore,
                $rejectedAfter,
                LayoutDiffContext::fromGondola($gondola, $this->productsInvolved($baseline, $proposed, $rejectedBefore, $rejectedAfter)),
            );

            $occupancy = $reportBuilder->buildOccupancyMetrics($result);

            $run->forceFill(array_merge($occupancy, [
                'status' => GenerationRunStatus::Completed,
                'finished_at' => now(),
                'duration_ms' => $run->elapsedMs(),
                'capacity_report' => $reportBuilder->buildCapacityReport($result, $this->templateId),
                'validation_report' => $result->output->validationReport->toArray(),
            ]))->save();

            $proposal = PlanogramReoptimizationProposal::create([
                'planogram_id' => $this->planogramId,
                'gondola_id' => $this->gondolaId,
                'generation_run_id' => $this->runId,
                // Sem mudanças não há decisão a tomar. O registro fica (prova que a análise rodou),
                // mas não entra na fila de aprovação — senão o usuário aprenderia a ignorá-la.
                'status' => $diff->hasChanges ? ProposalStatus::Pending : ProposalStatus::NoChanges,
                'trigger' => $this->trigger,
                'config_snapshot' => $this->config,
                'baseline_layout' => $serializer->toArray($baseline),
                'baseline_hash' => $baselineHash,
                'proposed_layout' => $serializer->toArray($proposed),
                'proposed_rejected' => $rejectedAfter,
                'diff_summary' => $diff->toArray(),
                'sales_period_start' => $this->config['start_date'] ?? null,
                'sales_period_end' => $this->config['end_date'] ?? null,
                'occupancy_before' => $this->previousOccupancy(),
                'occupancy_after' => $occupancy['occupancy_avg'] ?? null,
                'requested_by' => $this->userId,
            ]);

            Log::info('Proposta de reotimização calculada', [
                'proposal_id' => $proposal->id,
                'gondola_id' => $this->gondolaId,
                'status' => $proposal->status->value,
                'changes' => count($diff->entries),
            ]);

            if (! $diff->hasChanges) {
                $this->notify(
                    title: __('plannerate.reoptimization.notification.no_changes_title'),
                    message: __('plannerate.reoptimization.notification.no_changes_message'),
                    type: 'info',
                    proposalId: null,
                );

                return;
            }

            $this->notify(
                title: __('plannerate.reoptimization.notification.ready_title'),
                message: __('plannerate.reoptimization.notification.ready_message', [
                    'count' => count($diff->entries),
                    'gondola' => $gondola->name ?? '',
                ]),
                type: 'info',
                proposalId: (string) $proposal->id,
            );
        } catch (GenerationCancelledException $e) {
            // Cancelamento de negócio (ex.: nenhum produto elegível na nova janela de vendas).
            // Não é defeito: a proposta registra o motivo e a cadência já foi avançada.
            $run->markFailed($e->getMessage());

            PlanogramReoptimizationProposal::create([
                'planogram_id' => $this->planogramId,
                'gondola_id' => $this->gondolaId,
                'generation_run_id' => $this->runId,
                'status' => ProposalStatus::Failed,
                'trigger' => $this->trigger,
                'config_snapshot' => $this->config,
                'requested_by' => $this->userId,
                'error_message' => $e->getMessage(),
            ]);

            Log::info('Reotimização cancelada', [
                'run_id' => $this->runId,
                'gondola_id' => $this->gondolaId,
                'reason' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateReoptimizationProposalJob falhou', [
            'run_id' => $this->runId,
            'gondola_id' => $this->gondolaId,
            'tenant_id' => $this->tenantId,
            'error' => $e->getMessage(),
        ]);

        Tenant::query()->find($this->tenantId)?->makeCurrent();

        PlanogramGenerationRun::query()->find($this->runId)?->markFailed($e->getMessage());
    }

    /**
     * Produtos citados no diff, para o contexto traduzir IDs em nome/EAN/imagem.
     *
     * @param  Collection<int, PlacedSegment>  $baseline
     * @param  Collection<int, PlacedSegment>  $proposed
     * @param  list<array<string, mixed>>  $rejectedBefore
     * @param  list<array<string, mixed>>  $rejectedAfter
     */
    private function productsInvolved($baseline, $proposed, array $rejectedBefore, array $rejectedAfter): iterable
    {
        $ids = collect([$baseline, $proposed])
            ->flatMap(fn ($segments) => $segments->flatMap(fn ($segment) => $segment->layers->map(fn ($layer) => $layer->productId)))
            ->merge(array_column($rejectedBefore, 'product_id'))
            ->merge(array_column($rejectedAfter, 'product_id'))
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return Product::query()->whereIn('id', $ids)->get(['id', 'name', 'ean', 'image_url']);
    }

    /**
     * Ocupação da última geração aplicada — o "antes" que a tela compara com o "depois".
     */
    private function previousOccupancy(): ?float
    {
        $lastApplied = PlanogramGenerationRun::query()
            ->applied()
            ->where('gondola_id', $this->gondolaId)
            ->where('status', GenerationRunStatus::Completed)
            ->latest('created_at')
            ->first();

        return $lastApplied?->occupancy_avg;
    }

    private function notify(string $title, string $message, string $type, ?string $proposalId): void
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
                // URL relativa montada à mão: o worker não tem o host do tenant no request e
                // route() geraria o host errado.
                actionUrl: $proposalId !== null ? "/editor/reoptimization/{$proposalId}" : null,
                tenantId: $this->tenantId,
            ),
            context: 'GenerateReoptimizationProposalJob',
        );
    }
}
