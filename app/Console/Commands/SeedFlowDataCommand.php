<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Services\Flow\FlowStepTemplateAutoAssignmentResolver;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Planogram;
use Callcocam\LaravelRaptorPlannerate\Models\Workflow\GondolaWorkflow;
use Callcocam\LaravelRaptorPlannerate\Models\Workflow\PlanogramWorkflow;
use Callcocam\LaravelRaptorPlannerate\Support\WorkflowMorphMap;
use Callcocam\LaravelRaptor\Services\TenantDatabaseManager;
use Callcocam\LaravelRaptorFlow\Models\Flow;
use Callcocam\LaravelRaptorFlow\Models\FlowConfigStep;
use Callcocam\LaravelRaptorFlow\Models\FlowExecution;
use Callcocam\LaravelRaptorFlow\Models\FlowHistory;
use Callcocam\LaravelRaptorFlow\Models\FlowMetric;
use Callcocam\LaravelRaptorFlow\Models\FlowNotification;
use Callcocam\LaravelRaptorFlow\Models\FlowParticipant;
use Callcocam\LaravelRaptorFlow\Models\FlowPreset;
use Callcocam\LaravelRaptorFlow\Models\FlowPresetStep;
use Callcocam\LaravelRaptorFlow\Models\FlowStepTemplate;
use Callcocam\LaravelRaptorFlow\Services\FlowManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\warning;

/**
 * Seed das tabelas flow_*: Flow (fluxo), FlowStepTemplate do fluxo, FlowConfigSteps (planogramas), FlowExecution (gôndolas).
 * Cria o fluxo "Gerenciamento de planogramas", associa os step templates a ele e cria etapas/executions a partir do fluxo.
 */
class SeedFlowDataCommand extends Command
{
    /** Slug do fluxo padrão (gerenciamento de planogramas). */
    private const DEFAULT_FLOW_SLUG = 'planogramas';

    protected $signature = 'flow:seed
        {--client= : ID ou slug do cliente (opcional)}
        {--skip-configs : Pular criação de configs}
        {--skip-executions : Pular criação de executions}
        {--force : Apagar todos os dados flow_* e recriar (Flow, FlowStepTemplate, FlowConfigStep, FlowExecution, etc.)}';

    protected $description = 'Cria Flow (fluxo), FlowStepTemplates do fluxo, FlowConfigSteps e FlowExecutions para planogramas e gôndolas';

    protected int $configsCreated = 0;

    protected int $executionsCreated = 0;

    protected int $skipped = 0;

    public function handle(): int
    {
        info('🚀 Iniciando seed de dados flow (tabelas flow_*)...');

        if ($this->option('force')) {
            $this->wipeFlowData();
        }

        $clients = $this->getClients();
        if ($clients->isEmpty()) {
            warning('❌ Nenhum cliente com banco dedicado encontrado.');

            return self::FAILURE;
        }

        // Garante Flow e FlowStepTemplates no banco principal (landlord) antes de processar clientes.
        info('📌 Garantindo fluxo e templates no banco principal...');
        $flow = $this->ensureFlow();
        $flowTemplates = $this->ensureFlowStepTemplates($flow);
        if ($flowTemplates->isEmpty()) {
            warning('⚠️ Nenhum step template no fluxo. Crie templates no fluxo ou use os defaults.');
        }
        $this->newLine();

        $restoreDb = app(TenantDatabaseManager::class)->getDefaultDatabaseName();

        foreach ($clients as $client) {
            app(TenantDatabaseManager::class)->switchDefaultConnectionTo($client->database);
            config(['app.current_client_id' => $client->id]);
            $this->processClient($client);
        }

        app(TenantDatabaseManager::class)->setupConnection($restoreDb);

        $this->newLine();
        info('✅ Processo concluído!');
        $this->table(
            ['Métrica', 'Quantidade'],
            [
                ['Etapas (FlowConfigStep) criadas', $this->configsCreated],
                ['FlowExecutions criados', $this->executionsCreated],
                ['Itens ignorados (já existiam)', $this->skipped],
            ]
        );

        return self::SUCCESS;
    }

    protected function getClients()
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
     * Apaga todos os dados das tabelas flow_* (landlord), na ordem que respeita FKs.
     * Usado quando --force é passado para recriar tudo do zero.
     */
    protected function wipeFlowData(): void
    {
        info('🗑️  --force: apagando todos os dados flow_* no landlord...');

        FlowMetric::query()->forceDelete();
        FlowNotification::query()->forceDelete();
        FlowHistory::query()->forceDelete();
        FlowExecution::query()->forceDelete();
        FlowParticipant::query()->forceDelete();
        FlowConfigStep::query()->forceDelete();
        FlowPresetStep::query()->forceDelete();
        FlowPreset::query()->forceDelete();
        FlowStepTemplate::query()->forceDelete();
        Flow::query()->forceDelete();

        info('   Dados flow_* apagados.');
        $this->newLine();
    }

    protected function processClient(Client $client): void
    {
        info("📦 Processando cliente: {$client->name}");

        $flow = $this->ensureFlow();
        $templates = $this->ensureFlowStepTemplates($flow);
        if ($templates->isEmpty()) {
            warning('  ⚠️ Nenhum step template no fluxo. Crie templates no fluxo ou use os defaults.');
            $this->newLine();

            return;
        }

        if (! $this->option('skip-configs')) {
            $this->createConfigsForClient($client, $flow, $templates);
        }
        if (! $this->option('skip-executions')) {
            $this->createExecutionsForClient($client);
        }
        $this->newLine();
    }

    /**
     * Garante que o fluxo padrão existe (ex.: Gerenciamento de planogramas).
     */
    protected function ensureFlow(): Flow
    {
        $flow = Flow::findBySlug(self::DEFAULT_FLOW_SLUG);
        if ($flow) {
            return $flow;
        }

        return Flow::createWithSlug([
            'name' => 'Gerenciamento de planogramas',
            'slug' => self::DEFAULT_FLOW_SLUG,
            'status' => 'active',
        ]);
    }

    /**
     * Garante que o fluxo tem FlowStepTemplates. Se não tiver, vincula os existentes (flow_id null)
     * ao fluxo ou cria a partir dos defaults. Evita duplicar slug (unique na tabela).
     */
    protected function ensureFlowStepTemplates(Flow $flow): \Illuminate\Support\Collection
    {
        $templates = $flow->stepTemplates()->where('is_active', true)->orderBy('suggested_order')->get();
        if ($templates->isNotEmpty()) {
            $this->applyTemplateAutoAssignments($templates);
            $this->syncTemplateNeighbors($templates);

            return $templates;
        }

        foreach (FlowStepTemplate::getDefaultTemplates() as $row) {
            $slug = $row['slug'];
            $existing = FlowStepTemplate::where('slug', $slug)->first();
            if ($existing) {
                $existing->update(['flow_id' => $flow->id]);
            } else {
                FlowStepTemplate::create(array_merge($row, ['flow_id' => $flow->id, 'is_active' => true]));
            }
        }

        $templates = $flow->stepTemplates()->where('is_active', true)->orderBy('suggested_order')->get();
        $this->applyTemplateAutoAssignments($templates);
        $this->syncTemplateNeighbors($templates);

        return $templates;
    }

    protected function applyTemplateAutoAssignments(\Illuminate\Support\Collection $templates): void
    {
        if ($templates->isEmpty()) {
            return;
        }

        app(FlowStepTemplateAutoAssignmentResolver::class)->applyToTemplates($templates);
    }

    /**
     * Sincroniza os ponteiros anterior/próximo dos templates por suggested_order.
     *
     * Primeira etapa: previous = null
     * Última etapa: next = null
     */
    protected function syncTemplateNeighbors(\Illuminate\Support\Collection $templates): void
    {
        if ($templates->isEmpty()) {
            return;
        }

        $ordered = $templates
            ->sortBy(fn (FlowStepTemplate $template) => [(int) $template->suggested_order, (string) $template->id])
            ->values();

        foreach ($ordered as $index => $template) {
            $previous = $ordered->get($index - 1);
            $next = $ordered->get($index + 1);

            $previousId = $previous?->id ? (string) $previous->id : null;
            $nextId = $next?->id ? (string) $next->id : null;

            if (
                (string) ($template->template_previous_step_id ?? '') !== (string) ($previousId ?? '') ||
                (string) ($template->template_next_step_id ?? '') !== (string) ($nextId ?? '')
            ) {
                $template->update([
                    'template_previous_step_id' => $previousId,
                    'template_next_step_id' => $nextId,
                ]);
            }
        }
    }

    protected function createConfigsForClient(Client $client, Flow $flow, \Illuminate\Support\Collection $templates): void
    {
        $planogramIds = Planogram::where('client_id', $client->id)->pluck('id');
        if ($planogramIds->isEmpty()) {
            info('  📋 Nenhum planograma encontrado.');

            return;
        }

        info("  📋 Configs para {$planogramIds->count()} planogramas (fluxo: {$flow->name})...");
        $flowManager = app(FlowManager::class);
        $progress = $this->input->isInteractive() ? progress(label: '  Planogramas', steps: $planogramIds->count()) : null;
        $progress?->start();

        foreach ($planogramIds as $planogramId) {
            $this->createConfigForPlanogram($planogramId, $flow, $templates, $flowManager);
            $progress?->advance();
        }
        $progress?->finish();
    }

    protected function createConfigForPlanogram(string $planogramId, Flow $flow, \Illuminate\Support\Collection $templates, FlowManager $flowManager): void
    {
        $this->normalizeLegacyPlanogramConfigType($planogramId);

        $planogramWorkflow = PlanogramWorkflow::find($planogramId);
        if (! $planogramWorkflow) {
            return;
        }

        $existingSteps = $flowManager->getStepsFor($planogramWorkflow);
        if ($existingSteps->isNotEmpty()) {
            if (! $this->option('force')) {
                $this->skipped++;

                return;
            }
            FlowConfigStep::whereIn('configurable_type', WorkflowMorphMap::planogramWorkflowTypes())
                ->where('configurable_id', $planogramWorkflow->getWorkflowKey())
                ->delete();
        }

        $steps = [];
        $order = 1;
        foreach ($templates as $template) {
            $steps[] = [
                'flow_step_template_id' => $template->id,
                'order' => $order++,
                'default_role_id' => $template->default_role_id,
                'estimated_duration_days' => $template->estimated_duration_days ?? 2,
            ];
        }

        $flowManager->createStepsFor($planogramWorkflow, $steps, 'Config Planograma '.$planogramId, null);
        $this->configsCreated++;
    }

    protected function createExecutionsForClient(Client $client): void
    {
        $planogramIds = Planogram::where('client_id', $client->id)->pluck('id');
        if ($planogramIds->isEmpty()) {
            return;
        }

        $gondolas = DB::connection()
            ->table('gondolas')
            ->whereIn('planogram_id', $planogramIds->toArray())
            ->whereNull('deleted_at')
            ->get(['id', 'planogram_id']);

        if ($gondolas->isEmpty()) {
            info('  🏪 Nenhuma gôndola encontrada.');

            return;
        }

        info("  🏪 Executions para {$gondolas->count()} gôndolas...");
        $flowManager = app(FlowManager::class);
        $progress = $this->input->isInteractive() ? progress(label: '  Gôndolas', steps: $gondolas->count()) : null;
        $progress?->start();

        foreach ($gondolas as $gondola) {
            $this->createExecutionForGondola($gondola->id, $gondola->planogram_id, $flowManager);
            $progress?->advance();
        }
        $progress?->finish();
    }

    protected function createExecutionForGondola(string $gondolaId, string $planogramId, FlowManager $flowManager): void
    {
        $gondolaWorkflow = GondolaWorkflow::find($gondolaId);
        $planogramWorkflow = PlanogramWorkflow::find($planogramId);
        if (! $gondolaWorkflow || ! $planogramWorkflow) {
            return;
        }

        $existing = FlowExecution::whereIn('workable_type', WorkflowMorphMap::gondolaWorkflowTypes())
            ->where('workable_id', $gondolaId);

        if ($existing->exists()) {
            if (! $this->option('force')) {
                $this->skipped++;

                return;
            }
            $existing->forceDelete();
        }

        $steps = $flowManager->getStepsFor($planogramWorkflow);
        if ($steps->isEmpty()) {
            $this->skipped++;

            return;
        }

        try {
            $flowManager->createPendingExecution($gondolaWorkflow, $planogramWorkflow);
            $this->executionsCreated++;
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->skipped++;
        }
    }

    /**
     * Compatibilidade durante migração de namespace do workflow do planograma.
     */
    protected function normalizeLegacyPlanogramConfigType(string $planogramId): void
    {
        FlowConfigStep::query()
            ->where('configurable_id', $planogramId)
            ->where('configurable_type', WorkflowMorphMap::LEGACY_PLANOGRAM_WORKFLOW)
            ->update([
                'configurable_type' => PlanogramWorkflow::class,
            ]);
    }
}
