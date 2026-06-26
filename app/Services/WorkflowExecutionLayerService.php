<?php

namespace App\Services;

use App\Enums\ExecutionDivergenceStatus;
use App\Enums\ExecutionEvidenceType;
use App\Enums\WorkflowExecutionStatus;
use App\Enums\WorkflowHistoryAction;
use App\Models\User;
use App\Models\WorkflowExecutionDivergence;
use App\Models\WorkflowExecutionEvidence;
use App\Models\WorkflowExecutionEvidenceRequirement;
use App\Models\WorkflowGondolaExecution;
use App\Models\WorkflowHistory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Regras da camada de Execução em Loja: início automático, registro de
 * evidências e divergências, cálculo de obrigatoriedade e conclusão validada.
 *
 * Não recria a visualização (tela de print): apenas opera os dados de execução
 * acoplados a uma {@see WorkflowGondolaExecution} já na etapa Execução em Loja.
 */
class WorkflowExecutionLayerService
{
    /** Disco onde as evidências/fotos de divergência são persistidas. */
    private const DISK = 'public';

    public function __construct(
        private readonly WorkflowKanbanService $kanbanService,
    ) {}

    /**
     * Inicia automaticamente a execução quando ela está pendente e o usuário
     * abre a etapa Execução em Loja pela primeira vez (export §12).
     *
     * Idempotente: só atua sobre execuções `pending`; demais estados retornam
     * sem alteração. Registra histórico de início.
     */
    public function autoStartIfPending(WorkflowGondolaExecution $execution, User $actor): WorkflowGondolaExecution
    {
        if ($execution->status !== WorkflowExecutionStatus::Pending) {
            return $execution;
        }

        return $this->kanbanService->startPendingExecution($execution, $actor);
    }

    /**
     * Registra uma evidência (arquivo) na execução e grava histórico.
     *
     * @param  array{type?: string, module_label?: ?string, product_id?: ?string, notes?: ?string}  $data
     */
    public function addEvidence(
        WorkflowGondolaExecution $execution,
        User $actor,
        UploadedFile $file,
        array $data
    ): WorkflowExecutionEvidence {
        return DB::transaction(function () use ($execution, $actor, $file, $data) {
            $path = $file->store($this->evidenceDirectory($execution), self::DISK);

            $evidence = WorkflowExecutionEvidence::create([
                'user_id' => $actor->id,
                'workflow_gondola_execution_id' => $execution->id,
                'type' => $data['type'] ?? ExecutionEvidenceType::GeneralPhoto->value,
                'module_label' => $data['module_label'] ?? null,
                'product_id' => $data['product_id'] ?? null,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'notes' => $data['notes'] ?? null,
            ]);

            $this->recordHistory(
                $execution,
                $actor,
                WorkflowHistoryAction::EvidenceAdded,
                "Evidência adicionada ({$evidence->type->value})."
            );

            return $evidence;
        });
    }

    /**
     * Remove uma evidência (soft delete + apaga o arquivo) e grava histórico.
     */
    public function removeEvidence(
        WorkflowGondolaExecution $execution,
        WorkflowExecutionEvidence $evidence,
        User $actor
    ): void {
        DB::transaction(function () use ($execution, $evidence, $actor) {
            if ($evidence->file_path !== null) {
                Storage::disk(self::DISK)->delete($evidence->file_path);
            }

            $evidence->delete();

            $this->recordHistory(
                $execution,
                $actor,
                WorkflowHistoryAction::EvidenceRemoved,
                'Evidência removida.'
            );
        });
    }

    /**
     * Registra uma divergência na execução e grava histórico.
     *
     * @param  array{type: string, module_label?: ?string, shelf_label?: ?string, position_label?: ?string, product_id?: ?string, notes?: ?string}  $data
     * @param  array<int, UploadedFile>  $photos
     */
    public function addDivergence(
        WorkflowGondolaExecution $execution,
        User $actor,
        array $data,
        array $photos = []
    ): WorkflowExecutionDivergence {
        return DB::transaction(function () use ($execution, $actor, $data, $photos) {
            $storedPhotos = collect($photos)
                ->map(fn (UploadedFile $photo): string => $photo->store($this->divergenceDirectory($execution), self::DISK))
                ->all();

            $divergence = WorkflowExecutionDivergence::create([
                'user_id' => $actor->id,
                'workflow_gondola_execution_id' => $execution->id,
                'type' => $data['type'],
                'module_label' => $data['module_label'] ?? null,
                'shelf_label' => $data['shelf_label'] ?? null,
                'position_label' => $data['position_label'] ?? null,
                'product_id' => $data['product_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => ExecutionDivergenceStatus::Open->value,
                'photos' => $storedPhotos !== [] ? $storedPhotos : null,
            ]);

            $this->recordHistory(
                $execution,
                $actor,
                WorkflowHistoryAction::DivergenceRegistered,
                "Divergência registrada ({$divergence->type->value})."
            );

            return $divergence;
        });
    }

    /**
     * Atualiza o estado de uma divergência (justificar/resolver/analisar) e
     * grava histórico.
     *
     * @param  array{status: string, resolution_notes?: ?string}  $data
     */
    public function updateDivergence(
        WorkflowGondolaExecution $execution,
        WorkflowExecutionDivergence $divergence,
        User $actor,
        array $data
    ): WorkflowExecutionDivergence {
        return DB::transaction(function () use ($execution, $divergence, $actor, $data) {
            $status = ExecutionDivergenceStatus::from($data['status']);

            $divergence->update([
                'status' => $status->value,
                'resolution_notes' => $data['resolution_notes'] ?? $divergence->resolution_notes,
            ]);

            $action = $status === ExecutionDivergenceStatus::Resolved
                ? WorkflowHistoryAction::DivergenceResolved
                : WorkflowHistoryAction::DivergenceUpdated;

            $this->recordHistory(
                $execution,
                $actor,
                $action,
                "Divergência atualizada para {$status->value}."
            );

            return $divergence->fresh();
        });
    }

    /**
     * Conclui a execução em loja após validar evidências obrigatórias e
     * divergências pendentes (export §21–§23).
     *
     * Reaproveita {@see WorkflowKanbanService::complete}, que marca a execução
     * como `completed` e, quando aplicável, conclui o planograma
     * (`lifecycle_status = completed`, `completed_at`, `periodic_review_due_at`).
     *
     * @throws ValidationException quando há evidências obrigatórias faltando ou
     *                             divergências pendentes sem justificativa.
     */
    public function complete(WorkflowGondolaExecution $execution, User $actor, ?string $notes = null): WorkflowGondolaExecution
    {
        $this->assertCanComplete($execution);

        return $this->kanbanService->complete($execution, $actor, $notes);
    }

    /**
     * Garante que a execução atende às pré-condições de conclusão; lança
     * {@see ValidationException} com a chave do bloqueio quando não atende.
     */
    public function assertCanComplete(WorkflowGondolaExecution $execution): void
    {
        $summary = $this->evidenceSummary($execution);

        if (! $summary['satisfied']) {
            throw ValidationException::withMessages([
                'evidences' => 'Há evidências obrigatórias faltando para concluir a execução.',
            ]);
        }

        if ($this->pendingDivergencesCount($execution) > 0) {
            throw ValidationException::withMessages([
                'divergences' => 'Existem divergências pendentes sem justificativa.',
            ]);
        }
    }

    /**
     * Monta o payload completo (pesado) da camada de execução, consultado
     * apenas quando `canExecute` é verdadeiro (via Inertia::optional).
     *
     * @return array<string, mixed>
     */
    public function buildPayload(WorkflowGondolaExecution $execution, User $user): array
    {
        $execution->loadMissing([
            'currentResponsible:id,name',
            'startedBy:id,name',
            'evidences.user:id,name',
            'divergences.user:id,name',
        ]);

        $summary = $this->evidenceSummary($execution);
        $pendingDivergences = $this->pendingDivergencesCount($execution);

        return [
            'id' => $execution->id,
            'status' => $execution->status?->value,
            'responsible' => $execution->currentResponsible?->name,
            'started_by' => $execution->startedBy?->name,
            'started_at' => $execution->started_at?->toIso8601String(),
            'sla_date' => $execution->sla_date?->toIso8601String(),
            'sla_days_remaining' => $this->slaDaysRemaining($execution),
            'evidences' => $execution->evidences
                ->map(fn (WorkflowExecutionEvidence $evidence): array => $this->evidenceToArray($evidence))
                ->values()
                ->all(),
            'divergences' => $execution->divergences
                ->map(fn (WorkflowExecutionDivergence $divergence): array => $this->divergenceToArray($divergence))
                ->values()
                ->all(),
            'evidence_summary' => $summary,
            'pending_divergences_count' => $pendingDivergences,
            'can_complete' => $summary['satisfied']
                && $pendingDivergences === 0
                && $user->can('complete', $execution),
        ];
    }

    /**
     * Resumo de obrigatoriedade de evidências (X/Y por tipo + total).
     *
     * @return array{required: int, provided: int, satisfied: bool, breakdown: array<int, array{type: string, required: int, provided: int}>}
     */
    public function evidenceSummary(WorkflowGondolaExecution $execution): array
    {
        $required = $this->requiredEvidences($execution);
        $provided = $this->providedEvidenceCounts($execution);

        $breakdown = [];
        $totalRequired = 0;
        $totalProvided = 0;
        $satisfied = true;

        foreach ($required as $type => $requiredCount) {
            $providedCount = $provided[$type] ?? 0;
            $totalRequired += $requiredCount;
            $totalProvided += min($providedCount, $requiredCount);

            if ($providedCount < $requiredCount) {
                $satisfied = false;
            }

            $breakdown[] = [
                'type' => $type,
                'required' => $requiredCount,
                'provided' => $providedCount,
            ];
        }

        return [
            'required' => $totalRequired,
            'provided' => $totalProvided,
            'satisfied' => $satisfied,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Quantidade de evidências obrigatórias por tipo. Aplica a configuração do
     * tenant quando existe; senão usa o padrão (1 foto geral + 1 por módulo).
     *
     * @return array<string, int>
     */
    private function requiredEvidences(WorkflowGondolaExecution $execution): array
    {
        $moduleCount = $this->moduleCount($execution);
        $categoryId = $execution->gondola?->planogram?->category_id;

        $rules = WorkflowExecutionEvidenceRequirement::query()
            ->where(function ($query) use ($categoryId): void {
                $query->whereNull('category_id');

                if ($categoryId !== null) {
                    $query->orWhere('category_id', $categoryId);
                }
            })
            ->get();

        if ($rules->isEmpty()) {
            return [
                ExecutionEvidenceType::GeneralPhoto->value => 1,
                ExecutionEvidenceType::Module->value => $moduleCount,
            ];
        }

        $required = [];

        foreach ($rules as $rule) {
            $base = $rule->per_module ? $rule->min_count * $moduleCount : $rule->min_count;
            $type = $rule->type->value;
            $required[$type] = max($required[$type] ?? 0, $base);
        }

        return $required;
    }

    /**
     * Contagem de evidências já anexadas, agrupadas por tipo.
     *
     * @return array<string, int>
     */
    private function providedEvidenceCounts(WorkflowGondolaExecution $execution): array
    {
        return $execution->evidences()
            ->get(['type'])
            ->groupBy(fn (WorkflowExecutionEvidence $evidence): string => $evidence->type->value)
            ->map(fn (Collection $group): int => $group->count())
            ->all();
    }

    /**
     * Número de módulos (seções) da gôndola da execução.
     */
    private function moduleCount(WorkflowGondolaExecution $execution): int
    {
        $gondola = $execution->gondola;

        return $gondola !== null ? $gondola->sections()->count() : 0;
    }

    /**
     * Quantidade de divergências em estado que bloqueia a conclusão.
     */
    private function pendingDivergencesCount(WorkflowGondolaExecution $execution): int
    {
        return $execution->divergences()
            ->whereIn('status', [
                ExecutionDivergenceStatus::Open->value,
                ExecutionDivergenceStatus::InAnalysis->value,
            ])
            ->count();
    }

    /**
     * Dias restantes de SLA a partir de agora (negativo quando vencido).
     */
    private function slaDaysRemaining(WorkflowGondolaExecution $execution): ?int
    {
        if ($execution->sla_date === null) {
            return null;
        }

        return (int) ceil(now()->diffInDays($execution->sla_date, false));
    }

    /**
     * @return array<string, mixed>
     */
    private function evidenceToArray(WorkflowExecutionEvidence $evidence): array
    {
        return [
            'id' => $evidence->id,
            'type' => $evidence->type?->value,
            'module_label' => $evidence->module_label,
            'product_id' => $evidence->product_id,
            'file_url' => $evidence->file_path !== null
                ? Storage::disk(self::DISK)->url($evidence->file_path)
                : null,
            'file_name' => $evidence->file_name,
            'notes' => $evidence->notes,
            'created_by' => $evidence->user?->name,
            'created_at' => $evidence->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function divergenceToArray(WorkflowExecutionDivergence $divergence): array
    {
        return [
            'id' => $divergence->id,
            'type' => $divergence->type?->value,
            'module_label' => $divergence->module_label,
            'shelf_label' => $divergence->shelf_label,
            'position_label' => $divergence->position_label,
            'product_id' => $divergence->product_id,
            'notes' => $divergence->notes,
            'status' => $divergence->status?->value,
            'resolution_notes' => $divergence->resolution_notes,
            'photo_urls' => collect($divergence->photos ?? [])
                ->map(fn (string $path): string => Storage::disk(self::DISK)->url($path))
                ->all(),
            'created_by' => $divergence->user?->name,
            'created_at' => $divergence->created_at?->toIso8601String(),
        ];
    }

    /**
     * Caminho de armazenamento das evidências da execução (escopado por tenant).
     */
    private function evidenceDirectory(WorkflowGondolaExecution $execution): string
    {
        return 'execution-evidences/'.($execution->tenant_id ?? 'shared').'/'.$execution->id;
    }

    /**
     * Caminho de armazenamento das fotos de divergência (escopado por tenant).
     */
    private function divergenceDirectory(WorkflowGondolaExecution $execution): string
    {
        return 'execution-divergences/'.($execution->tenant_id ?? 'shared').'/'.$execution->id;
    }

    private function recordHistory(
        WorkflowGondolaExecution $execution,
        User $actor,
        WorkflowHistoryAction $action,
        string $description
    ): void {
        WorkflowHistory::create([
            'user_id' => $actor->id,
            'workflow_gondola_execution_id' => $execution->id,
            'action' => $action,
            'description' => $description,
            'snapshot' => $execution->toArray(),
            'can_restore' => false,
            'performed_at' => now(),
        ]);
    }
}
