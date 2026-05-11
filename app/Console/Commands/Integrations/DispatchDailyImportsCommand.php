<?php

namespace App\Console\Commands\Integrations;

use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Services\Integrations\ResolvedIntegrationConfigResolver;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ReflectionClass;

#[Signature('integrations:daily-imports {--clear : Limpa tabelas antes do dispatch respeitando os paths configurados} {--no-finalize : Não dispara finalização em jobs que suportam runFinalize} {--type= : Filtra integrações por tipo, por exemplo products}')]
#[Description('Inicia a busca diária para os paths configurados nas integrações ativas')]
class DispatchDailyImportsCommand extends Command
{
    public function handle(ResolvedIntegrationConfigResolver $configResolver): int
    {
        $this->logStep(1, 'Iniciando importações diárias usando requests.paths.');

        $integrations = TenantIntegration::query()
            ->with(['tenant:id,name,slug,status,database'])
            ->where('is_active', true)
            ->orderBy('tenant_id')
            ->get(['id', 'tenant_id', 'integration_type', 'identifier', 'is_active', 'last_sync']);

        if ($integrations->isEmpty()) {
            $this->warn('Nenhuma integração ativa encontrada para a busca diária.');

            return self::SUCCESS;
        }

        $this->logStep(2, sprintf(
            'Integrações ativas encontradas: %d.',
            $integrations->count(),
        ));

        $dispatchPlan = $this->dispatchPlan($integrations, $configResolver);

        if (($type = $this->option('type')) !== null && $type !== '') {
            $dispatchPlan = $dispatchPlan->filter(
                fn (array $planRow): bool => (string) $planRow['resource'] === $type,
            );
        }

        $this->logStep(3, sprintf(
            'Paths despacháveis encontrados: %d.',
            $dispatchPlan->count(),
        ));

        if ($dispatchPlan->isEmpty()) {
            $this->warn('Nenhum path configurado possui job de importação registrado.');

            return self::SUCCESS;
        }

        if ((bool) $this->option('clear')) {
            $this->clearTablesForActiveIntegrations($dispatchPlan);
        }

        $this->info(sprintf(
            'Integrações ativas encontradas para importação diária: %d',
            $dispatchPlan
                ->map(fn (array $planRow): string => (string) $planRow['integration']->id)
                ->unique()
                ->count(),
        ));

        $dispatches = [];

        $this->logStep(4, 'Despachando jobs por path configurado.');

        foreach ($dispatchPlan as $planRow) {
            /** @var TenantIntegration $integration */
            $integration = $planRow['integration'];
            $jobClass = $planRow['job_class'];
            dispatch($this->makeImportJob($jobClass, (string) $integration->id));

            $dispatches[] = [
                (string) $integration->id,
                $this->tenantLabel($integration),
                $planRow['resource'],
                class_basename($jobClass),
            ];
        }

        $this->logStep(5, sprintf(
            'Dispatch finalizado. Jobs enfileirados: %d.',
            count($dispatches),
        ));

        $this->table(
            ['Integração', 'Tenant', 'Status tenant', 'API', 'Identificador', 'Última sync'],
            $dispatchPlan
                ->pluck('integration')
                ->unique(fn (TenantIntegration $integration): string => (string) $integration->id)
                ->map(fn (TenantIntegration $integration): array => $this->integrationTableRow($integration))
                ->values()
                ->all(),
        );

        $this->table(
            ['Integração', 'Tenant', 'Path', 'Job'],
            $dispatches,
        );

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, TenantIntegration>  $integrations
     * @return Collection<int, array{integration: TenantIntegration, resource: string, target_table: string, job_class: class-string}>
     */
    private function dispatchPlan(Collection $integrations, ResolvedIntegrationConfigResolver $configResolver): Collection
    {
        $jobMap = $this->importJobMap();

        return $integrations
            ->flatMap(function (TenantIntegration $integration) use ($configResolver, $jobMap): array {
                $resourceRequests = $configResolver->resolve($integration)->resourceRequests();
                $rows = [];

                foreach ($resourceRequests as $resource => $request) {
                    $targetTable = trim((string) ($request['target_table'] ?? $resource));
                    $jobClass = $jobMap[$resource] ?? $jobMap[$targetTable] ?? null;

                    if (! is_string($jobClass) || ! class_exists($jobClass)) {
                        $this->warn(sprintf(
                            'Path [%s] da integração %s ignorado: job de importação não registrado.',
                            $resource,
                            (string) $integration->id,
                        ));

                        continue;
                    }

                    $rows[] = [
                        'integration' => $integration,
                        'resource' => (string) $resource,
                        'target_table' => $targetTable,
                        'job_class' => $jobClass,
                    ];
                }

                if ($resourceRequests === []) {
                    $this->warn(sprintf(
                        'Integração %s ignorada: nenhum requests.paths configurado para a API [%s].',
                        (string) $integration->id,
                        (string) $integration->integration_type,
                    ));
                }

                return $rows;
            })
            ->values();
    }

    /**
     * @return array<string, class-string>
     */
    private function importJobMap(): array
    {
        $configured = config('integrations.import_jobs', []);

        if (! is_array($configured)) {
            return [];
        }

        return collect($configured)
            ->filter(fn (mixed $jobClass, mixed $key): bool => is_string($key) && is_string($jobClass))
            ->all();
    }

    private function tenantLabel(TenantIntegration $integration): string
    {
        if ($integration->tenant === null) {
            return sprintf('Tenant não encontrado (%s)', $integration->tenant_id);
        }

        return sprintf('%s (%s)', $integration->tenant->name, $integration->tenant->slug);
    }

    /**
     * @return list<string>
     */
    private function integrationTableRow(TenantIntegration $integration): array
    {
        return [
            (string) $integration->id,
            $this->tenantLabel($integration),
            (string) ($integration->tenant?->status ?? '-'),
            (string) $integration->integration_type,
            (string) ($integration->identifier ?: '-'),
            $integration->last_sync?->toDateTimeString() ?? '-',
        ];
    }

    /**
     * @param  Collection<int, array{integration: TenantIntegration, resource: string, target_table: string, job_class: class-string}>  $dispatchPlan
     */
    private function clearTablesForActiveIntegrations(Collection $dispatchPlan): void
    {
        $clearTables = $this->clearTablesMap();
        $tenantRows = [];

        foreach ($dispatchPlan as $planRow) {
            $tenant = $planRow['integration']->tenant;
            if (! $tenant instanceof Tenant) {
                continue;
            }

            $tables = $clearTables[$planRow['resource']] ?? $clearTables[$planRow['target_table']] ?? [];
            if ($tables === []) {
                continue;
            }

            $tenantId = (string) $tenant->id;
            $tenantRows[$tenantId] ??= [
                'tenant' => $tenant,
                'tables' => [],
            ];

            $tenantRows[$tenantId]['tables'] = array_values(array_unique([
                ...$tenantRows[$tenantId]['tables'],
                ...$tables,
            ]));
        }

        $tenants = collect($tenantRows)->values();

        if ($tenants->isEmpty()) {
            $this->warn('Limpeza ignorada: nenhum tenant válido ou tabela configurada para os paths ativos.');

            return;
        }

        $this->warn('Iniciando limpeza de tabelas para integrações ativas...');

        foreach ($tenants as $tenantRow) {
            /** @var Tenant $tenant */
            $tenant = $tenantRow['tenant'];
            $tables = $tenantRow['tables'];

            $tenant->execute(function () use ($tenant, $tables): void {
                $connection = (string) (config('multitenancy.tenant_database_connection_name') ?: config('database.default'));

                foreach ($tables as $table) {
                    DB::connection($connection)->table($table)->where('tenant_id', (string) $tenant->id)->delete();
                }
            });
        }

        $this->info(sprintf(
            'Limpeza concluída para %d tenant(s) ativo(s). Tabelas: %s',
            $tenants->count(),
            collect($tenantRows)->pluck('tables')->flatten()->unique()->implode(', '),
        ));
    }

    /**
     * @return array<string, list<string>>
     */
    private function clearTablesMap(): array
    {
        $configured = config('integrations.import_clear_tables', []);

        if (! is_array($configured)) {
            return [];
        }

        return collect($configured)
            ->filter(fn (mixed $tables, mixed $key): bool => is_string($key) && is_array($tables))
            ->map(fn (array $tables): array => collect($tables)
                ->filter(fn (mixed $table): bool => is_string($table) && $table !== '')
                ->values()
                ->all())
            ->all();
    }

    private function makeImportJob(string $jobClass, string $integrationId): object
    {
        $arguments = ['integrationId' => $integrationId];
        $constructor = (new ReflectionClass($jobClass))->getConstructor();

        if ($constructor !== null) {
            $supportsRunFinalize = collect($constructor->getParameters())
                ->contains(fn (\ReflectionParameter $parameter): bool => $parameter->getName() === 'runFinalize');

            if ($supportsRunFinalize) {
                $arguments['runFinalize'] = ! (bool) $this->option('no-finalize');
            }
        }

        return app($jobClass, $arguments);
    }

    private function logStep(int $step, string $message): void
    {
        $formatted = sprintf('[Passo %02d] %s', $step, $message);

        $this->info($formatted);
        Log::info($formatted, [
            'command' => 'integrations:daily-imports',
            'step' => $step,
        ]);
    }
}
