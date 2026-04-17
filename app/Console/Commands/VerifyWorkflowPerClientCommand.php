<?php

namespace App\Console\Commands;

use App\Models\Client;
use Callcocam\LaravelRaptorPlannerate\Models\Workflow\GondolaWorkflow;
use Callcocam\LaravelRaptorPlannerate\Models\Workflow\PlanogramWorkflow;
use Callcocam\LaravelRaptor\Services\TenantDatabaseManager;
use Callcocam\LaravelRaptorFlow\Models\FlowConfigStep;
use Callcocam\LaravelRaptorFlow\Models\FlowExecution;
use Callcocam\LaravelRaptorFlow\Models\FlowStepTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

/**
 * Verifica por cliente se flow (FlowConfigSteps, FlowExecution) foi criado.
 * Use flow:seed quando faltar.
 */
class VerifyWorkflowPerClientCommand extends Command
{
    protected $signature = 'workflow:verify
        {--client= : ID ou slug de um único cliente (opcional)}';

    protected $description = 'Lista por cliente: planogramas, FlowConfigSteps, gôndolas, FlowExecutions (tabelas flow_*)';

    public function handle(): int
    {
        info('Verificando flow por cliente...');

        $clients = $this->getClients();
        if ($clients->isEmpty()) {
            warning('Nenhum cliente com banco dedicado encontrado.');

            return self::FAILURE;
        }

        $restoreDb = app(TenantDatabaseManager::class)->getDefaultDatabaseName();
        $rows = [];

        foreach ($clients as $client) {
            app(TenantDatabaseManager::class)->switchDefaultConnectionTo($client->database);
            config(['app.current_client_id' => $client->id]);

            $planogramIds = DB::connection()->table('planograms')
                ->where('client_id', $client->id)
                ->whereNull('deleted_at')
                ->pluck('id')
                ->toArray();

            $planogramsTotal = count($planogramIds);
            if ($planogramIds === []) {
                $rows[] = [
                    $client->name,
                    '0',
                    '0',
                    '0',
                    '0',
                    '0',
                ];

                continue;
            }

            $gondolaIds = DB::connection()
                ->table('gondolas')
                ->whereIn('planogram_id', $planogramIds)
                ->whereNull('deleted_at')
                ->pluck('id')
                ->toArray();
            $gondolasTotal = count($gondolaIds);

            $planogramsWithFlowConfig = FlowConfigStep::query()
                ->where('configurable_type', PlanogramWorkflow::class)
                ->whereIn('configurable_id', $planogramIds)
                ->select('configurable_id')
                ->groupBy('configurable_id')
                ->get()
                ->count();

            $gondolasWithFlowExecution = $gondolaIds === []
                ? 0
                : FlowExecution::query()
                    ->where('workable_type', GondolaWorkflow::class)
                    ->whereIn('workable_id', $gondolaIds)
                    ->select('workable_id')
                    ->groupBy('workable_id')
                    ->get()
                    ->count();

            $flowTemplatesCount = FlowStepTemplate::where('is_active', true)->count();

            $rows[] = [
                $client->name,
                (string) $planogramsTotal,
                (string) $planogramsWithFlowConfig,
                (string) $gondolasTotal,
                (string) $gondolasWithFlowExecution,
                (string) $flowTemplatesCount,
            ];
        }

        app(TenantDatabaseManager::class)->setupConnection($restoreDb);

        $this->table(
            ['Cliente', 'Planogramas', 'Com FlowConfigSteps', 'Gôndolas', 'Com FlowExecution', 'FlowStepTemplates'],
            $rows
        );

        $this->newLine();
        info('Use flow:seed quando faltar configs ou executions.');

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

        return Client::whereNotNull('database')->where('database', '!=', '')->orderBy('name')->get();
    }
}
