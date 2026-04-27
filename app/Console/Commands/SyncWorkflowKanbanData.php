<?php

namespace App\Console\Commands;

use App\Enums\WorkflowExecutionStatus;
use App\Models\Gondola;
use App\Models\Planogram;
use App\Models\Tenant;
use App\Models\WorkflowGondolaExecution;
use App\Models\WorkflowPlanogramStep;
use App\Models\WorkflowTemplate;
use App\Services\WorkflowPlanogramStepService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\select;

class SyncWorkflowKanbanData extends Command
{
    protected $signature = 'workflow:sync-kanban-data
        {--tenant=* : Tenant ID(s) para processar}
        {--planogram=* : Planogram ID(s) especificos para processar}
        {--dry-run : Mostra o que seria criado sem gravar dados}';

    protected $description = 'Sincroniza steps de workflow e executions de gondolas para planogramas existentes.';

    public function __construct(private readonly WorkflowPlanogramStepService $stepService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenants = $this->tenantsToProcess();

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado para processar.');

            return self::FAILURE;
        }

        $exitCode = self::SUCCESS;

        foreach ($tenants as $tenant) {
            /** @var Tenant $tenant */
            $result = $tenant->execute(fn (): int => $this->syncTenant($tenant));

            if ($result !== self::SUCCESS) {
                $exitCode = self::FAILURE;
            }
        }

        return $exitCode;
    }

    private function syncTenant(Tenant $tenant): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $planogramIds = $this->planogramIdsToProcess($tenant);

        $query = Planogram::query()
            ->when($planogramIds !== [], fn ($planograms) => $planograms->whereIn('id', $planogramIds))
            ->orderBy('id');

        $totalPlanograms = (clone $query)->count();

        if ($totalPlanograms === 0) {
            $this->warn("Tenant {$tenant->name}: nenhum planograma encontrado.");

            return self::SUCCESS;
        }

        $summary = [
            'planograms' => 0,
            'steps_created' => 0,
            'executions_created' => 0,
            'executions_existing' => 0,
            'planograms_without_steps' => 0,
            'gondolas' => 0,
        ];

        $this->info(sprintf(
            'Tenant %s: sincronizando %d planograma(s)%s.',
            $tenant->name,
            $totalPlanograms,
            $dryRun ? ' (dry-run)' : '',
        ));

        $query->chunkById(100, function (Collection $planograms) use ($dryRun, &$summary): void {
            foreach ($planograms as $planogram) {
                /** @var Planogram $planogram */
                $result = $dryRun
                    ? $this->previewPlanogram($planogram)
                    : DB::transaction(fn (): array => $this->syncPlanogram($planogram));

                foreach ($result as $key => $value) {
                    $summary[$key] += $value;
                }
            }
        });

        $this->table(
            ['Planogramas', 'Gondolas', 'Steps criados', 'Executions criadas', 'Executions existentes', 'Sem steps'],
            [[
                $summary['planograms'],
                $summary['gondolas'],
                $summary['steps_created'],
                $summary['executions_created'],
                $summary['executions_existing'],
                $summary['planograms_without_steps'],
            ]],
        );

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function tenantsToProcess(): Collection
    {
        $tenantIds = array_values(array_filter((array) $this->option('tenant')));

        if ($tenantIds !== []) {
            return Tenant::query()
                ->whereIn('id', $tenantIds)
                ->orderBy('name')
                ->get();
        }

        if (! $this->input->isInteractive()) {
            return Tenant::query()
                ->orderBy('name')
                ->get();
        }

        $tenants = Tenant::query()
            ->orderBy('name')
            ->get(['id', 'name', 'database']);

        if ($tenants->isEmpty()) {
            return $tenants;
        }

        $selectedTenantId = select(
            label: 'Qual tenant deseja sincronizar?',
            options: ['__all__' => 'Todos os tenants'] + $tenants
                ->mapWithKeys(fn (Tenant $tenant): array => [$tenant->id => $tenant->name])
                ->all(),
            default: '__all__',
        );

        if ($selectedTenantId === '__all__') {
            return $tenants;
        }

        return $tenants->where('id', $selectedTenantId)->values();
    }

    /**
     * @return list<string>
     */
    private function planogramIdsToProcess(Tenant $tenant): array
    {
        $planogramIds = array_values(array_filter((array) $this->option('planogram')));

        if ($planogramIds !== []) {
            return $planogramIds;
        }

        if (! $this->input->isInteractive()) {
            return [];
        }

        $planograms = Planogram::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($planograms->isEmpty()) {
            return [];
        }

        $selectedPlanogramId = select(
            label: "Qual planograma deseja sincronizar em {$tenant->name}?",
            options: ['__all__' => 'Todos os planogramas'] + $planograms
                ->mapWithKeys(fn (Planogram $planogram): array => [$planogram->id => $planogram->name])
                ->all(),
            default: '__all__',
        );

        if ($selectedPlanogramId === '__all__') {
            return [];
        }

        return [(string) $selectedPlanogramId];
    }

    /**
     * @return array{planograms: int, steps_created: int, executions_created: int, executions_existing: int, planograms_without_steps: int, gondolas: int}
     */
    private function syncPlanogram(Planogram $planogram): array
    {
        $stepsBefore = $planogram->workflowSteps()->count();
        $steps = $this->stepService->syncForPlanogram($planogram);
        $stepsAfter = $planogram->workflowSteps()->count();

        $firstStep = $this->firstAvailableStep($steps);

        if (! $firstStep instanceof WorkflowPlanogramStep) {
            return $this->result(stepsCreated: max(0, $stepsAfter - $stepsBefore), planogramsWithoutSteps: 1);
        }

        return $this->syncExecutionsForPlanogram(
            planogram: $planogram,
            firstStep: $firstStep,
            stepsCreated: max(0, $stepsAfter - $stepsBefore),
            dryRun: false,
        );
    }

    /**
     * @return array{planograms: int, steps_created: int, executions_created: int, executions_existing: int, planograms_without_steps: int, gondolas: int}
     */
    private function previewPlanogram(Planogram $planogram): array
    {
        $steps = $planogram->workflowSteps()
            ->with(['template:id,suggested_order', 'availableUsers:id'])
            ->get()
            ->sortBy(fn (WorkflowPlanogramStep $step): int => $step->template?->suggested_order ?? PHP_INT_MAX)
            ->values();

        $stepsToCreate = $this->missingWorkflowTemplateCount($planogram);
        $firstStep = $this->firstAvailableStep($steps);

        if (! $firstStep instanceof WorkflowPlanogramStep && $stepsToCreate > 0) {
            return $this->syncExecutionsForPlanogram(
                planogram: $planogram,
                firstStep: null,
                stepsCreated: $stepsToCreate,
                dryRun: true,
            );
        }

        if (! $firstStep instanceof WorkflowPlanogramStep) {
            return $this->result(planogramsWithoutSteps: 1);
        }

        return $this->syncExecutionsForPlanogram(
            planogram: $planogram,
            firstStep: $firstStep,
            stepsCreated: $stepsToCreate,
            dryRun: true,
        );
    }

    /**
     * @return array{planograms: int, steps_created: int, executions_created: int, executions_existing: int, planograms_without_steps: int, gondolas: int}
     */
    private function syncExecutionsForPlanogram(
        Planogram $planogram,
        ?WorkflowPlanogramStep $firstStep,
        int $stepsCreated,
        bool $dryRun,
    ): array {
        $created = 0;
        $existing = 0;
        $gondolas = 0;
        $responsibleId = $firstStep?->availableUsers()->value('users.id');

        Gondola::query()
            ->where('planogram_id', $planogram->id)
            ->select(['id', 'tenant_id'])
            ->chunkById(100, function (Collection $chunk) use ($firstStep, $responsibleId, $dryRun, &$created, &$existing, &$gondolas): void {
                $gondolaIds = $chunk->pluck('id')->map(fn (mixed $id): string => (string) $id)->all();
                $existingGondolaIds = WorkflowGondolaExecution::withTrashed()
                    ->whereIn('gondola_id', $gondolaIds)
                    ->pluck('gondola_id')
                    ->map(fn (mixed $id): string => (string) $id)
                    ->all();

                $existingByGondolaId = array_flip($existingGondolaIds);

                foreach ($chunk as $gondola) {
                    /** @var Gondola $gondola */
                    $gondolas++;

                    if (isset($existingByGondolaId[$gondola->id])) {
                        $existing++;

                        continue;
                    }

                    $created++;

                    if ($dryRun) {
                        continue;
                    }

                    if (! $firstStep instanceof WorkflowPlanogramStep) {
                        throw new \LogicException('Nao foi possivel criar execution sem step de workflow.');
                    }

                    WorkflowGondolaExecution::query()->create([
                        'tenant_id' => $gondola->tenant_id,
                        'gondola_id' => $gondola->id,
                        'workflow_planogram_step_id' => $firstStep->id,
                        'status' => WorkflowExecutionStatus::Pending,
                        'current_responsible_id' => $responsibleId,
                    ]);
                }
            });

        return $this->result(
            stepsCreated: $stepsCreated,
            executionsCreated: $created,
            executionsExisting: $existing,
            gondolas: $gondolas,
        );
    }

    /**
     * @param  Collection<int, WorkflowPlanogramStep>  $steps
     */
    private function firstAvailableStep(Collection $steps): ?WorkflowPlanogramStep
    {
        return $steps->first(fn (WorkflowPlanogramStep $step): bool => ! $step->is_skipped);
    }

    private function missingWorkflowTemplateCount(Planogram $planogram): int
    {
        $publishedTemplateIds = WorkflowTemplate::query()
            ->where('status', 'published')
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id);

        $existingTemplateIds = $planogram->workflowSteps()
            ->pluck('workflow_template_id')
            ->map(fn (mixed $id): string => (string) $id);

        return $publishedTemplateIds->diff($existingTemplateIds)->count();
    }

    /**
     * @return array{planograms: int, steps_created: int, executions_created: int, executions_existing: int, planograms_without_steps: int, gondolas: int}
     */
    private function result(
        int $stepsCreated = 0,
        int $executionsCreated = 0,
        int $executionsExisting = 0,
        int $planogramsWithoutSteps = 0,
        int $gondolas = 0,
    ): array {
        return [
            'planograms' => 1,
            'steps_created' => $stepsCreated,
            'executions_created' => $executionsCreated,
            'executions_existing' => $executionsExisting,
            'planograms_without_steps' => $planogramsWithoutSteps,
            'gondolas' => $gondolas,
        ];
    }
}
