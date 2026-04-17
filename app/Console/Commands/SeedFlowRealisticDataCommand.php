<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Services\Flow\FlowStepTemplateAutoAssignmentResolver;
use Callcocam\LaravelRaptorPlannerate\Models\Workflow\GondolaWorkflow;
use Callcocam\LaravelRaptorPlannerate\Support\WorkflowMorphMap;
use Callcocam\LaravelRaptor\Services\TenantDatabaseManager;
use Callcocam\LaravelRaptorFlow\Enums\FlowAction;
use Callcocam\LaravelRaptorFlow\Enums\FlowNotificationPriority;
use Callcocam\LaravelRaptorFlow\Enums\FlowNotificationType;
use Callcocam\LaravelRaptorFlow\Enums\FlowParticipantRole;
use Callcocam\LaravelRaptorFlow\Enums\FlowStatus;
use Callcocam\LaravelRaptorFlow\Models\FlowConfigStep;
use Callcocam\LaravelRaptorFlow\Models\FlowExecution;
use Callcocam\LaravelRaptorFlow\Models\FlowHistory;
use Callcocam\LaravelRaptorFlow\Models\FlowMetric;
use Callcocam\LaravelRaptorFlow\Models\FlowNotification;
use Callcocam\LaravelRaptorFlow\Models\FlowParticipant;
use Callcocam\LaravelRaptorFlow\Models\FlowStepTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\warning;

/**
 * Gera dados realistas e aleatórios de workflow para análise de gráficos.
 *
 * Preserva Flow e FlowStepTemplate existentes; apenas recria FlowExecution,
 * FlowHistory, FlowMetric, FlowNotification e FlowParticipant com distribuição
 * variada de status, etapas e datas retroativas.
 */
class SeedFlowRealisticDataCommand extends Command
{
    protected $signature = 'flow:seed-realistic
        {--client= : ID ou slug do cliente (opcional)}
        {--force : Apagar dados de execução existentes e recriar}';

    protected $description = 'Gera massa de dados realista e aleatória de workflow (execuções, histórico, métricas, notificações) para análise de gráficos';

    /** @var array<string, list<string>> stepTemplateId => [userIds] */
    protected array $userPool = [];

    protected int $executionsCreated = 0;

    protected int $historyCreated = 0;

    protected int $metricsCreated = 0;

    protected int $notificationsCreated = 0;

    protected int $participantsCreated = 0;

    /** @var array<string, int> */
    protected array $statusCounts = [
        'pending' => 0,
        'in_progress' => 0,
        'paused' => 0,
        'completed' => 0,
    ];

    public function handle(): int
    {
        info('🚀 Iniciando geração de dados realistas de workflow...');

        $clients = $this->getClients();
        if ($clients->isEmpty()) {
            warning('❌ Nenhum cliente com banco dedicado encontrado.');

            return self::FAILURE;
        }

        if ($this->option('force')) {
            $this->wipeExecutionData();
        }

        $this->buildGlobalUserPool();

        $restoreDb = app(TenantDatabaseManager::class)->getDefaultDatabaseName();

        foreach ($clients as $client) {
            app(TenantDatabaseManager::class)->switchDefaultConnectionTo($client->database);
            config(['app.current_client_id' => $client->id]);
            $this->processClient($client);
        }

        app(TenantDatabaseManager::class)->setupConnection($restoreDb);

        $this->newLine();
        info('✅ Geração concluída!');
        $this->table(
            ['Métrica', 'Quantidade'],
            [
                ['Execuções criadas', $this->executionsCreated],
                ['  → Pending', $this->statusCounts['pending']],
                ['  → InProgress', $this->statusCounts['in_progress']],
                ['  → Paused', $this->statusCounts['paused']],
                ['  → Completed', $this->statusCounts['completed']],
                ['Registros de histórico', $this->historyCreated],
                ['Métricas', $this->metricsCreated],
                ['Notificações', $this->notificationsCreated],
                ['Participantes', $this->participantsCreated],
            ]
        );

        return self::SUCCESS;
    }

    protected function getClients(): Collection
    {
        $clientOption = $this->option('client');
        if ($clientOption) {
            $bySlug = Client::where('slug', $clientOption)->whereNotNull('database')->get();
            if ($bySlug->isNotEmpty()) {
                return $bySlug;
            }

            return Client::where('id', $clientOption)->whereNotNull('database')->get();
        }

        $clients = Client::whereNotNull('database')->where('database', '!=', '')->get();
        if ($clients->count() > 1 && $this->input->isInteractive()) {
            $selected = multiselect(
                label: 'Selecione os clientes para processar:',
                options: $clients->pluck('name', 'id')->toArray(),
                default: $clients->pluck('id')->toArray(),
            );

            return $clients->whereIn('id', $selected);
        }

        return $clients;
    }

    /**
     * Remove apenas dados de execução (preserva Flow, FlowStepTemplate, FlowConfigStep).
     */
    protected function wipeExecutionData(): void
    {
        info('🗑️  --force: apagando dados de execução (preservando Flow, Templates e ConfigSteps)...');

        FlowMetric::query()->forceDelete();
        FlowNotification::query()->forceDelete();
        FlowHistory::query()->forceDelete();
        FlowParticipant::query()->forceDelete();
        FlowExecution::query()->forceDelete();

        info('   Dados de execução apagados.');
        $this->newLine();
    }

    protected function buildGlobalUserPool(): void
    {
        $templates = FlowStepTemplate::where('is_active', true)->get();
        $this->templateAutoResolver()->applyToTemplates($templates);

        foreach ($templates as $template) {
            $suggestedUsers = $template->users ?? [];
            if (! empty($suggestedUsers)) {
                $this->userPool[$template->id] = array_values(array_filter($suggestedUsers));
            }
        }
    }

    /**
     * @return list<string>
     */
    protected function getUsersForStep(FlowConfigStep $step): array
    {
        $templateId = $step->flow_step_template_id;
        if (! empty($this->userPool[$templateId])) {
            return $this->userPool[$templateId];
        }

        $roleId = $step->default_role_id ?: $step->stepTemplate?->default_role_id;
        if ($roleId) {
            $tenantId = (string) ($step->stepTemplate?->tenant_id ?? '') ?: null;
            $usersByRole = $this->templateAutoResolver()->getSuggestedUsersByRole((string) $roleId, $tenantId);

            if (! empty($usersByRole)) {
                return $usersByRole;
            }
        }

        return [];
    }

    protected function templateAutoResolver(): FlowStepTemplateAutoAssignmentResolver
    {
        return app(FlowStepTemplateAutoAssignmentResolver::class);
    }

    protected function processClient(Client $client): void
    {
        info("📦 Processando cliente: {$client->name}");

        $planogramIds = DB::table('planograms')
            ->where('client_id', $client->id)
            ->whereNull('deleted_at')
            ->pluck('id');

        if ($planogramIds->isEmpty()) {
            info('  📋 Nenhum planograma encontrado.');

            return;
        }

        foreach ($planogramIds as $planogramId) {
            $this->processGondolasForPlanogram($planogramId);
        }

        $this->newLine();
    }

    protected function processGondolasForPlanogram(string $planogramId): void
    {
        $steps = FlowConfigStep::whereIn('configurable_type', WorkflowMorphMap::planogramWorkflowTypes())
            ->where('configurable_id', $planogramId)
            ->where('is_active', true)
            ->orderBy('order')
            ->with('stepTemplate')
            ->get();

        if ($steps->isEmpty()) {
            return;
        }

        $gondolas = DB::connection()
            ->table('gondolas')
            ->where('planogram_id', $planogramId)
            ->whereNull('deleted_at')
            ->pluck('id');

        if ($gondolas->isEmpty()) {
            return;
        }

        info("  📋 Planograma {$planogramId}: {$gondolas->count()} gôndolas, {$steps->count()} etapas");

        foreach ($gondolas as $gondolaId) {
            $this->simulateGondolaWorkflow((string) $gondolaId, $steps);
        }
    }

    /**
     * @param  Collection<int, FlowConfigStep>  $steps
     */
    protected function simulateGondolaWorkflow(string $gondolaId, Collection $steps): void
    {
        $existing = FlowExecution::whereIn('workable_type', WorkflowMorphMap::gondolaWorkflowTypes())
            ->where('workable_id', $gondolaId)
            ->first();

        if ($existing) {
            if (! $this->option('force')) {
                return;
            }
            FlowMetric::whereIn('workable_type', WorkflowMorphMap::gondolaWorkflowTypes())->where('workable_id', $gondolaId)->forceDelete();
            FlowNotification::whereIn('notifiable_type', WorkflowMorphMap::gondolaWorkflowTypes())->where('notifiable_id', $gondolaId)->forceDelete();
            FlowHistory::whereIn('workable_type', WorkflowMorphMap::gondolaWorkflowTypes())->where('workable_id', $gondolaId)->forceDelete();
            FlowParticipant::whereIn('participable_id', $steps->pluck('id'))
                ->where('participable_type', FlowConfigStep::class)
                ->forceDelete();
            $existing->forceDelete();
        }

        $stepCount = $steps->count();
        $lastIndex = $stepCount - 1;
        $targetStepIndex = $this->pickTargetStepIndex($stepCount);
        $finalStatus = $this->pickFinalStatus($targetStepIndex, $lastIndex);

        $baseDate = now()->subDays(rand(30, 90))->subHours(rand(0, 23));
        $currentTime = $baseDate->copy();

        $targetStep = $steps[$targetStepIndex];
        $totalPausedMinutes = 0;
        $currentResponsibleId = null;
        $executionStartedBy = null;
        $executionStartedAt = null;

        $journeySteps = $steps->slice(0, $targetStepIndex + 1)->values();

        foreach ($journeySteps as $journeyIndex => $step) {
            $isCurrentStep = ($journeyIndex === $targetStepIndex);
            $users = $this->getUsersForStep($step);
            $userId = ! empty($users) ? $users[array_rand($users)] : null;

            $estimatedMinutes = max(($step->estimated_duration_days ?? 2) * 24 * 60, 60);
            $stepDuration = (int) ($estimatedMinutes * (rand(30, 200) / 100));

            $stepStartedAt = $currentTime->copy()->addMinutes(rand(0, 240));
            $slaDate = $stepStartedAt->copy()->addDays($step->estimated_duration_days ?? 2);
            $stepCompletedAt = $stepStartedAt->copy()->addMinutes($stepDuration);

            $stepPausedMinutes = 0;
            $hasPause = rand(1, 100) <= 20;
            if ($hasPause) {
                $stepPausedMinutes = rand(30, 480);
                $stepCompletedAt->addMinutes($stepPausedMinutes);
            }

            $wasOverdue = $stepCompletedAt->isAfter($slaDate);

            if ($journeyIndex === 0) {
                $executionStartedAt = $stepStartedAt;
                $executionStartedBy = $userId;

                $this->createHistoryRecord($gondolaId, $step, FlowAction::Start, [
                    'user_id' => $userId,
                    'performed_at' => $stepStartedAt,
                    'notes' => 'Workflow iniciado',
                ]);
                $this->createNotificationRecord($gondolaId, $step, $userId, FlowNotificationType::Assigned, 'Execução iniciada', $stepStartedAt);
            }

            if ($hasPause && $userId) {
                $pauseAt = $stepStartedAt->copy()->addMinutes(rand(30, max(60, $stepDuration - 60)));
                $resumeAt = $pauseAt->copy()->addMinutes($stepPausedMinutes);
                $this->createHistoryRecord($gondolaId, $step, FlowAction::Pause, [
                    'user_id' => $userId,
                    'performed_at' => $pauseAt,
                ]);
                $this->createHistoryRecord($gondolaId, $step, FlowAction::Resume, [
                    'user_id' => $userId,
                    'performed_at' => $resumeAt,
                ]);
            }

            $hasReassign = rand(1, 100) <= 15 && count($users) > 1;
            if ($hasReassign) {
                $previousUser = $userId;
                $otherUsers = array_filter($users, fn ($u) => $u !== $userId);
                if (! empty($otherUsers)) {
                    $userId = $otherUsers[array_rand($otherUsers)];
                    $reassignAt = $stepStartedAt->copy()->addMinutes(rand(10, max(20, $stepDuration / 2)));
                    $this->createHistoryRecord($gondolaId, $step, FlowAction::Reassign, [
                        'user_id' => $previousUser,
                        'previous_responsible_id' => $previousUser,
                        'new_responsible_id' => $userId,
                        'performed_at' => $reassignAt,
                        'notes' => 'Reatribuição durante etapa',
                    ]);
                    $this->createNotificationRecord($gondolaId, $step, $userId, FlowNotificationType::Assigned, 'Responsabilidade atribuída', $reassignAt);
                }
            }

            $currentResponsibleId = $userId;
            $totalPausedMinutes += $stepPausedMinutes;

            if ($userId) {
                $this->createParticipantRecord($step, $userId);
            }

            if (! $isCurrentStep) {
                $nextStep = $journeySteps[$journeyIndex + 1];
                $durationMinutes = (int) $stepStartedAt->diffInMinutes($stepCompletedAt);

                $this->createHistoryRecord($gondolaId, $step, FlowAction::Move, [
                    'from_step_id' => $step->id,
                    'to_step_id' => $nextStep->id,
                    'user_id' => $userId,
                    'previous_responsible_id' => $userId,
                    'performed_at' => $stepCompletedAt,
                    'duration_in_step_minutes' => $durationMinutes,
                    'sla_at_transition' => $slaDate,
                    'was_overdue' => $wasOverdue,
                    'snapshot' => [
                        'flow_config_step_id' => $step->id,
                        'flow_step_template_id' => $step->flow_step_template_id,
                        'status' => FlowStatus::InProgress->value,
                        'current_responsible_id' => $userId,
                        'started_at' => $stepStartedAt->toIso8601String(),
                        'sla_date' => $slaDate->toIso8601String(),
                    ],
                ]);

                $this->createNotificationRecord($gondolaId, $nextStep, $userId, FlowNotificationType::Moved, 'Etapa movida', $stepCompletedAt);

                if ($wasOverdue) {
                    $this->createNotificationRecord($gondolaId, $step, $userId, FlowNotificationType::Overdue, 'Etapa concluída com atraso', $stepCompletedAt);
                }

                $this->createMetricRecord($gondolaId, $step, $stepStartedAt, $stepCompletedAt, $stepPausedMinutes, $slaDate);

                $currentTime = $stepCompletedAt->copy();
            } else {
                if ($finalStatus === FlowStatus::Completed) {
                    $this->createHistoryRecord($gondolaId, $step, FlowAction::Complete, [
                        'user_id' => $userId,
                        'performed_at' => $stepCompletedAt,
                    ]);
                    $this->createNotificationRecord($gondolaId, $step, $userId, FlowNotificationType::Completed, 'Workflow concluído', $stepCompletedAt);
                    $this->createMetricRecord($gondolaId, $step, $stepStartedAt, $stepCompletedAt, $stepPausedMinutes, $slaDate);
                } elseif ($finalStatus === FlowStatus::InProgress) {
                    $this->createMetricRecord($gondolaId, $step, $stepStartedAt, null, 0, $slaDate);
                }
            }
        }

        $executionData = $this->buildExecutionData(
            gondolaId: $gondolaId,
            targetStep: $targetStep,
            finalStatus: $finalStatus,
            executionStartedAt: $executionStartedAt,
            executionStartedBy: $executionStartedBy,
            currentResponsibleId: $currentResponsibleId,
            totalPausedMinutes: $totalPausedMinutes,
            baseDate: $baseDate,
            lastCompletedAt: $currentTime,
        );

        FlowExecution::create($executionData);
        $this->executionsCreated++;
        $this->statusCounts[$finalStatus->value]++;
    }

    protected function pickTargetStepIndex(int $stepCount): int
    {
        if ($stepCount <= 1) {
            return 0;
        }

        $weights = [];
        $lastIndex = $stepCount - 1;
        for ($i = 0; $i <= $lastIndex; $i++) {
            if ($i === 0) {
                $weights[$i] = 15;
            } elseif ($i === $lastIndex) {
                $weights[$i] = 25;
            } else {
                $weights[$i] = 60 / max($lastIndex - 1, 1);
            }
        }

        return $this->weightedRandom($weights);
    }

    protected function pickFinalStatus(int $targetStepIndex, int $lastIndex): FlowStatus
    {
        if ($targetStepIndex === $lastIndex) {
            $roll = rand(1, 100);
            if ($roll <= 60) {
                return FlowStatus::Completed;
            }
            if ($roll <= 90) {
                return FlowStatus::InProgress;
            }

            return FlowStatus::Paused;
        }

        if ($targetStepIndex === 0) {
            $roll = rand(1, 100);
            if ($roll <= 50) {
                return FlowStatus::Pending;
            }
            if ($roll <= 90) {
                return FlowStatus::InProgress;
            }

            return FlowStatus::Paused;
        }

        $roll = rand(1, 100);
        if ($roll <= 40) {
            return FlowStatus::Pending;
        }
        if ($roll <= 75) {
            return FlowStatus::InProgress;
        }

        return FlowStatus::Paused;
    }

    /**
     * @param  array<int, float|int>  $weights
     */
    protected function weightedRandom(array $weights): int
    {
        $total = (int) (array_sum($weights) * 100);
        $random = rand(1, $total);
        $cumulative = 0;

        foreach ($weights as $index => $weight) {
            $cumulative += (int) ($weight * 100);
            if ($random <= $cumulative) {
                return $index;
            }
        }

        return array_key_last($weights);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildExecutionData(
        string $gondolaId,
        FlowConfigStep $targetStep,
        FlowStatus $finalStatus,
        ?Carbon $executionStartedAt,
        ?string $executionStartedBy,
        ?string $currentResponsibleId,
        int $totalPausedMinutes,
        Carbon $baseDate,
        Carbon $lastCompletedAt,
    ): array {
        $estimatedDays = (int) ($targetStep->estimated_duration_days ?? 2);
        $slaDate = $executionStartedAt
            ? $executionStartedAt->copy()->addDays($estimatedDays)
            : $baseDate->copy()->addDays($estimatedDays);

        $data = [
            'workable_type' => GondolaWorkflow::class,
            'workable_id' => $gondolaId,
            'flow_config_step_id' => $targetStep->id,
            'flow_step_template_id' => $targetStep->flow_step_template_id,
            'status' => $finalStatus,
            'estimated_duration_days' => $estimatedDays,
            'current_responsible_id' => null,
            'execution_started_by' => null,
            'started_at' => null,
            'completed_at' => null,
            'sla_date' => $slaDate,
            'paused_at' => null,
            'paused_duration_minutes' => 0,
        ];

        if (in_array($finalStatus, [FlowStatus::InProgress, FlowStatus::Paused, FlowStatus::Completed], true)) {
            $data['current_responsible_id'] = $currentResponsibleId;
            $data['execution_started_by'] = $executionStartedBy;
            $data['started_at'] = $executionStartedAt;
        }

        if ($finalStatus === FlowStatus::Completed) {
            $data['completed_at'] = $lastCompletedAt;
            $data['paused_duration_minutes'] = $totalPausedMinutes;
        }

        if ($finalStatus === FlowStatus::Paused) {
            $data['paused_at'] = now()->subMinutes(rand(30, 480));
            $data['paused_duration_minutes'] = $totalPausedMinutes;
        }

        if ($finalStatus === FlowStatus::InProgress) {
            $data['paused_duration_minutes'] = $totalPausedMinutes;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createHistoryRecord(string $gondolaId, FlowConfigStep $step, FlowAction $action, array $attributes = []): void
    {
        FlowHistory::create(array_merge([
            'workable_type' => GondolaWorkflow::class,
            'workable_id' => $gondolaId,
            'flow_config_step_id' => $step->id,
            'action' => $action,
            'performed_at' => $attributes['performed_at'] ?? now(),
        ], $attributes));

        $this->historyCreated++;
    }

    protected function createMetricRecord(
        string $gondolaId,
        FlowConfigStep $step,
        Carbon $startedAt,
        ?Carbon $completedAt,
        int $pausedMinutes,
        Carbon $slaDate,
    ): void {
        $transitionAt = $completedAt ?? now();
        $totalDuration = (int) $startedAt->diffInMinutes($transitionAt);
        $effectiveWork = max($totalDuration - $pausedMinutes, 0);
        $estimatedMinutes = (int) (($step->estimated_duration_days ?? 2) * 24 * 60);
        $deviation = $estimatedMinutes > 0 ? $effectiveWork - $estimatedMinutes : null;
        $isOnTime = $transitionAt->lessThanOrEqualTo($slaDate);
        $isRework = rand(1, 100) <= 10;

        FlowMetric::create([
            'workable_type' => GondolaWorkflow::class,
            'workable_id' => $gondolaId,
            'flow_config_step_id' => $step->id,
            'flow_step_template_id' => $step->flow_step_template_id,
            'total_duration_minutes' => $totalDuration,
            'effective_work_minutes' => $effectiveWork,
            'estimated_duration_minutes' => $estimatedMinutes > 0 ? $estimatedMinutes : null,
            'deviation_minutes' => $deviation,
            'is_on_time' => $isOnTime,
            'is_rework' => $isRework,
            'rework_count' => $isRework ? 1 : 0,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'calculated_at' => $transitionAt,
            'metadata' => [
                'paused_duration_minutes' => $pausedMinutes,
            ],
        ]);

        $this->metricsCreated++;
    }

    protected function createNotificationRecord(
        string $gondolaId,
        FlowConfigStep $step,
        ?string $userId,
        FlowNotificationType $type,
        string $title,
        Carbon $createdAt,
    ): void {
        if (! $userId) {
            return;
        }

        $isRead = rand(1, 100) <= 70;

        FlowNotification::create([
            'user_id' => $userId,
            'notifiable_type' => GondolaWorkflow::class,
            'notifiable_id' => $gondolaId,
            'flow_config_step_id' => $step->id,
            'type' => $type,
            'priority' => $this->randomPriority(),
            'title' => $title,
            'message' => $this->notificationMessage($type, $step),
            'link' => null,
            'is_read' => $isRead,
            'read_at' => $isRead ? $createdAt->copy()->addMinutes(rand(5, 1440)) : null,
            'metadata' => [],
        ]);

        $this->notificationsCreated++;
    }

    protected function createParticipantRecord(FlowConfigStep $step, string $userId): void
    {
        $exists = FlowParticipant::where('participable_type', FlowConfigStep::class)
            ->where('participable_id', $step->id)
            ->where('user_id', $userId)
            ->exists();

        if ($exists) {
            return;
        }

        FlowParticipant::create([
            'user_id' => $userId,
            'participable_type' => FlowConfigStep::class,
            'participable_id' => $step->id,
            'role_in_step' => FlowParticipantRole::Assignee,
            'is_pre_assigned' => false,
            'assigned_at' => now(),
            'metadata' => [],
        ]);

        $this->participantsCreated++;
    }

    protected function randomPriority(): FlowNotificationPriority
    {
        $cases = FlowNotificationPriority::cases();

        return $cases[array_rand($cases)];
    }

    protected function notificationMessage(FlowNotificationType $type, FlowConfigStep $step): string
    {
        $stepName = $step->name ?? $step->stepTemplate?->name ?? 'etapa';

        return match ($type) {
            FlowNotificationType::Assigned => "Você recebeu a etapa '{$stepName}' para executar.",
            FlowNotificationType::Moved => "A execução foi movida para a etapa '{$stepName}'.",
            FlowNotificationType::Completed => "O workflow foi concluído na etapa '{$stepName}'.",
            FlowNotificationType::Overdue => "A etapa '{$stepName}' foi concluída com atraso.",
            FlowNotificationType::Reminder => "Lembrete: a etapa '{$stepName}' aguarda ação.",
            FlowNotificationType::Mentioned => "Você foi mencionado na etapa '{$stepName}'.",
        };
    }
}
