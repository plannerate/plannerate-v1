<?php

namespace App\Console\Commands\Integrations;

use App\Jobs\Integrations\Maintenance\RecalculateTenantMonthlySalesSummariesJob;
use App\Models\Tenant;
use App\Services\Integrations\Support\RecalculateMonthlySalesSummariesService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RecalculateMonthlySalesSummaries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monthly-sales:recalculate 
                            {--verify : Verifica se a soma está correta sem recalcular}
                            {--tenant= : Recalcula apenas para um tenant específico}
                            {--month= : Recalcula apenas para um mês específico (formato: YYYY-MM)}
                            {--sync : Executa imediatamente em vez de despachar jobs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalcula a tabela monthly_sales_summaries agregando dados da tabela sales';

    /**
     * Execute the console command.
     */
    public function handle(RecalculateMonthlySalesSummariesService $recalculateMonthlySalesSummariesService): int
    {
        $verifyOnly = (bool) $this->option('verify');
        $tenantId = $this->tenantOption();
        $monthOption = $this->option('month');
        $month = is_string($monthOption) && $monthOption !== '' ? $monthOption : null;
        $tenants = $this->getTenants($tenantId);

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant ativo encontrado.');

            return self::SUCCESS;
        }

        if ($verifyOnly) {
            return $this->verifyTenants($tenants, $month);
        }

        $sync = (bool) $this->option('sync');

        foreach ($tenants as $tenant) {
            if ($sync) {
                $summary = $recalculateMonthlySalesSummariesService->recalculate($tenant, $month);
                $this->line(sprintf(
                    '%s: %d venda(s) vinculada(s), %d resumo(s) removido(s), %d inserido(s), %d resumo(s) vinculado(s).',
                    $summary['tenant_name'],
                    $summary['sales_linked'],
                    $summary['deleted'],
                    $summary['inserted'],
                    $summary['summaries_linked'],
                ));

                continue;
            }

            RecalculateTenantMonthlySalesSummariesJob::dispatch((string) $tenant->id, $month);
        }

        $message = $sync
            ? 'Recálculo mensal executado.'
            : sprintf('%d job(s) de recálculo mensal despachado(s).', $tenants->count());

        $this->info($message);

        return self::SUCCESS;
    }

    private function tenantOption(): ?string
    {
        $tenantId = $this->option('tenant');

        return is_string($tenantId) && $tenantId !== '' ? $tenantId : null;
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function getTenants(?string $tenantId): Collection
    {
        $query = Tenant::query()->where('status', 'active');

        if ($tenantId) {
            $query->whereKey($tenantId);
        }

        return $query->get(['id', 'name', 'database']);
    }

    private function tenantConnectionName(): string
    {
        $connection = config('multitenancy.tenant_database_connection_name');

        return is_string($connection) && $connection !== '' ? $connection : (string) config('database.default');
    }

    /**
     * Verifica se a soma na monthly_sales_summaries está correta comparando com sales
     */
    private function verifyTenants(Collection $tenants, ?string $month): int
    {
        $exitCode = self::SUCCESS;

        foreach ($tenants as $tenant) {
            $this->newLine();
            $this->info(sprintf('🏢 Verificando tenant %s (%s)', $tenant->name, $tenant->id));

            $tenantExitCode = $tenant->execute(
                fn (): int => $this->verifySummaries($this->tenantConnectionName(), (string) $tenant->id, $month)
            );

            if ($tenantExitCode !== self::SUCCESS) {
                $exitCode = self::FAILURE;
            }
        }

        return $exitCode;
    }

    private function verifySummaries(string $connection, ?string $tenantId, ?string $month): int
    {
        $this->info('🔍 Verificando integridade dos dados...');
        $driver = DB::connection($connection)->getDriverName();

        // Calcula totais diretamente no banco para evitar esgotamento de memória
        $this->info('📊 Calculando totais de sales...');
        $salesTotals = DB::connection($connection)
            ->table('sales')
            ->select([
                DB::raw('SUM(total_sale_quantity) as total_quantity'),
                DB::raw('SUM(total_sale_value) as total_value'),
                DB::raw('SUM(margem_contribuicao) as total_margem'),
            ])
            ->whereNotNull('sale_date')
            ->whereNotNull('codigo_erp')
            ->whereNull('deleted_at');

        if ($tenantId) {
            $salesTotals->where('tenant_id', $tenantId);
        }

        if ($month) {
            $salesTotals->whereRaw($this->monthFilterExpression($driver, 'sale_date'), [$month]);
        }

        $salesTotal = $salesTotals->first();

        $this->info('📊 Calculando totais de monthly_sales_summaries...');
        $summaryTotals = DB::connection($connection)
            ->table('monthly_sales_summaries')
            ->select([
                DB::raw('SUM(total_sale_quantity) as total_quantity'),
                DB::raw('SUM(total_sale_value) as total_value'),
                DB::raw('SUM(margem_contribuicao) as total_margem'),
            ])
            ->whereNotNull('sale_month')
            ->whereNotNull('codigo_erp');

        if ($tenantId) {
            $summaryTotals->where('tenant_id', $tenantId);
        }

        if ($month) {
            $summaryTotals->whereRaw($this->monthFilterExpression($driver, 'sale_month'), [$month]);
        }

        $summaryTotal = $summaryTotals->first();

        // Compara os totais
        $tolerance = 0.01;
        $qtyDiff = abs(($salesTotal->total_quantity ?? 0) - ($summaryTotal->total_quantity ?? 0));
        $valueDiff = abs(($salesTotal->total_value ?? 0) - ($summaryTotal->total_value ?? 0));
        $margemDiff = abs(($salesTotal->total_margem ?? 0) - ($summaryTotal->total_margem ?? 0));

        $hasErrors = $qtyDiff > $tolerance || $valueDiff > $tolerance || $margemDiff > $tolerance;

        // Exibe resultados
        if (! $hasErrors) {
            $this->info('✅ Todas as verificações passaram! Os dados estão corretos.');
            $this->table(
                ['Métrica', 'Sales', 'Monthly Summaries', 'Diferença', 'Status'],
                [
                    [
                        'Quantidade Total',
                        number_format($salesTotal->total_quantity ?? 0),
                        number_format($summaryTotal->total_quantity ?? 0),
                        number_format($qtyDiff, 2),
                        $qtyDiff <= $tolerance ? '✅' : '❌',
                    ],
                    [
                        'Valor Total',
                        'R$ '.number_format($salesTotal->total_value ?? 0, 2, ',', '.'),
                        'R$ '.number_format($summaryTotal->total_value ?? 0, 2, ',', '.'),
                        'R$ '.number_format($valueDiff, 2, ',', '.'),
                        $valueDiff <= $tolerance ? '✅' : '❌',
                    ],
                    [
                        'Margem Total',
                        'R$ '.number_format($salesTotal->total_margem ?? 0, 2, ',', '.'),
                        'R$ '.number_format($summaryTotal->total_margem ?? 0, 2, ',', '.'),
                        'R$ '.number_format($margemDiff, 2, ',', '.'),
                        $margemDiff <= $tolerance ? '✅' : '❌',
                    ],
                ]
            );

            return 0;
        }

        $this->error('❌ Diferenças encontradas nos totais:');
        $this->newLine();
        $this->warn('Diferença em Quantidade: '.number_format($qtyDiff, 2));
        $this->warn('Diferença em Valor: R$ '.number_format($valueDiff, 2, ',', '.'));
        $this->warn('Diferença em Margem: R$ '.number_format($margemDiff, 2, ',', '.'));
        $this->newLine();
        $this->table(
            ['Métrica', 'Sales', 'Monthly Summaries', 'Diferença'],
            [
                [
                    'Quantidade Total',
                    number_format($salesTotal->total_quantity ?? 0),
                    number_format($summaryTotal->total_quantity ?? 0),
                    number_format($qtyDiff, 2),
                ],
                [
                    'Valor Total',
                    'R$ '.number_format($salesTotal->total_value ?? 0, 2, ',', '.'),
                    'R$ '.number_format($summaryTotal->total_value ?? 0, 2, ',', '.'),
                    'R$ '.number_format($valueDiff, 2, ',', '.'),
                ],
                [
                    'Margem Total',
                    'R$ '.number_format($salesTotal->total_margem ?? 0, 2, ',', '.'),
                    'R$ '.number_format($summaryTotal->total_margem ?? 0, 2, ',', '.'),
                    'R$ '.number_format($margemDiff, 2, ',', '.'),
                ],
            ]
        );

        return 1;
    }

    private function monthFilterExpression(string $driver, string $column): string
    {
        return match ($driver) {
            'pgsql' => "TO_CHAR({$column}, 'YYYY-MM') = ?",
            'sqlite' => "strftime('%Y-%m', {$column}) = ?",
            default => "TO_CHAR({$column}, 'YYYY-MM') = ?",
        };
    }
}
