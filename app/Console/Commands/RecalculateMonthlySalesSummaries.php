<?php

namespace App\Console\Commands;

use Callcocam\LaravelRaptorPlannerate\Models\Editor\MonthlySalesSummary;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Sale;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecalculateMonthlySalesSummaries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monthly-sales:recalculate 
                            {--verify : Verifica se a soma está correta sem recalcular}
                            {--client-id= : Recalcula apenas para um cliente específico}
                            {--month= : Recalcula apenas para um mês específico (formato: YYYY-MM)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalcula a tabela monthly_sales_summaries agregando dados da tabela sales';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $verifyOnly = $this->option('verify');
        $clientId = $this->option('client-id');
        $month = $this->option('month');

        if ($verifyOnly) {
            return $this->verifySummaries($clientId, $month);
        }

        $this->info('🔄 Iniciando recálculo de monthly_sales_summaries...');

        // Limpa a tabela (ou apenas registros específicos)
        if ($clientId || $month) {
            $query = MonthlySalesSummary::query();
            if ($clientId) {
                $query->where('client_id', $clientId);
            }
            if ($month) {
                $query->whereRaw("DATE_TRUNC('month', sale_month)::date = ?", ["{$month}-01"]);
            }
            $deleted = $query->delete();
            $this->info("🗑️  Removidos {$deleted} registros existentes");
        } else {
            $this->warn('⚠️  Limpando TODA a tabela monthly_sales_summaries...');
            if (!$this->confirm('Tem certeza que deseja continuar?')) {
                $this->error('Operação cancelada.');
                return 1;
            }
            $deleted = MonthlySalesSummary::query()->delete();
            $this->info("🗑️  Removidos {$deleted} registros");
        }

        // Agrega dados de sales
        $this->info('📊 Agregando dados de sales...');
        $aggregated = $this->aggregateSales($clientId, $month);

        if (empty($aggregated)) {
            $this->warn('⚠️  Nenhum dado encontrado para agregar.');
            return 0;
        }

        // Insere na tabela monthly_sales_summaries
        $this->info("💾 Inserindo {$aggregated->count()} registros...");
        $this->insertSummaries($aggregated);

        // Verifica se está correto
        $this->info('✅ Verificando se a soma está correta...');
        $this->verifySummaries($clientId, $month);

        $this->info('✨ Recálculo concluído com sucesso!');
        return 0;
    }

    /**
     * Agrega dados da tabela sales por mês
     */
    private function aggregateSales(?string $clientId, ?string $month)
    {
        $query = Sale::query()
            ->select([
                'tenant_id',
                'client_id',
                'store_id',
                'codigo_erp',
                'ean',
                'product_id',
                'promotion',
                DB::raw("DATE_TRUNC('month', sale_date)::date as sale_month"),
                DB::raw('SUM(acquisition_cost) as acquisition_cost'),
                DB::raw('SUM(sale_price) as sale_price'),
                DB::raw('SUM(total_profit_margin) as total_profit_margin'),
                DB::raw('SUM(total_sale_quantity) as total_sale_quantity'),
                DB::raw('SUM(total_sale_value) as total_sale_value'),
                DB::raw('SUM(margem_contribuicao) as margem_contribuicao'),
                DB::raw('MAX(extra_data) as extra_data_sample'),
            ])
            ->whereNotNull('sale_date')
            ->whereNotNull('codigo_erp')
            ->groupBy([
                'tenant_id',
                'client_id',
                'store_id',
                'codigo_erp',
                'ean',
                'product_id',
                'promotion',
                DB::raw("DATE_TRUNC('month', sale_date)"),
            ]);

        if ($clientId) {
            $query->where('client_id', $clientId);
        }

        if ($month) {
            $query->whereRaw("DATE_TRUNC('month', sale_date)::date = ?", ["{$month}-01"]);
        }

        return $query->get();
    }

    /**
     * Insere os dados agregados na tabela monthly_sales_summaries
     */
    private function insertSummaries($aggregated)
    {
        $bar = $this->output->createProgressBar($aggregated->count());
        $bar->start();

        $chunks = $aggregated->chunk(500);

        foreach ($chunks as $chunk) {
            $data = $chunk->map(function ($item) {
                // Processa extra_data - usa uma amostra (pode ser melhorado para agregar valores)
                $extraData = null;
                if ($item->extra_data_sample) {
                    try {
                        $decoded = json_decode($item->extra_data_sample, true);
                        if (is_array($decoded)) {
                            $extraData = $decoded;
                        }
                    } catch (\Exception $e) {
                        // Se falhar, usa null
                    }
                }

                return [
                    'id' => Str::ulid()->toString(),
                    'tenant_id' => $item->tenant_id,
                    'client_id' => $item->client_id,
                    'store_id' => $item->store_id,
                    'product_id' => $item->product_id,
                    'ean' => $item->ean,
                    'codigo_erp' => $item->codigo_erp,
                    'acquisition_cost' => $item->acquisition_cost ?? 0,
                    'sale_price' => $item->sale_price ?? 0,
                    'total_profit_margin' => $item->total_profit_margin ?? 0,
                    'sale_month' => $item->sale_month,
                    'promotion' => $item->promotion ?? 'N',
                    'total_sale_quantity' => $item->total_sale_quantity ?? 0,
                    'total_sale_value' => $item->total_sale_value ?? 0,
                    'margem_contribuicao' => $item->margem_contribuicao ?? 0,
                    'extra_data' => $extraData ? json_encode($extraData) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->toArray();

            MonthlySalesSummary::insert($data);
            $bar->advance($chunk->count());
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Verifica se a soma na monthly_sales_summaries está correta comparando com sales
     */
    private function verifySummaries(?string $clientId, ?string $month): int
    {
        $this->info('🔍 Verificando integridade dos dados...');

        // Calcula totais diretamente no banco para evitar esgotamento de memória
        $this->info('📊 Calculando totais de sales...');
        $salesTotals = Sale::query()
            ->select([
                DB::raw('SUM(total_sale_quantity) as total_quantity'),
                DB::raw('SUM(total_sale_value) as total_value'),
                DB::raw('SUM(margem_contribuicao) as total_margem'),
            ])
            ->whereNotNull('sale_date')
            ->whereNotNull('codigo_erp');

        if ($clientId) {
            $salesTotals->where('client_id', $clientId);
        }

        if ($month) {
            $salesTotals->whereRaw("DATE_TRUNC('month', sale_date)::date = ?", ["{$month}-01"]);
        }

        $salesTotal = $salesTotals->first();

        $this->info('📊 Calculando totais de monthly_sales_summaries...');
        $summaryTotals = MonthlySalesSummary::query()
            ->withoutGlobalScopes()
            ->select([
                DB::raw('SUM(total_sale_quantity) as total_quantity'),
                DB::raw('SUM(total_sale_value) as total_value'),
                DB::raw('SUM(margem_contribuicao) as total_margem'),
            ])
            ->whereNotNull('sale_month')
            ->whereNotNull('codigo_erp');

        if ($clientId) {
            $summaryTotals->where('client_id', $clientId);
        }

        if ($month) {
            $summaryTotals->whereRaw("DATE_TRUNC('month', sale_month)::date = ?", ["{$month}-01"]);
        }

        $summaryTotal = $summaryTotals->first();

        // Compara os totais
        $tolerance = 0.01;
        $qtyDiff = abs(($salesTotal->total_quantity ?? 0) - ($summaryTotal->total_quantity ?? 0));
        $valueDiff = abs(($salesTotal->total_value ?? 0) - ($summaryTotal->total_value ?? 0));
        $margemDiff = abs(($salesTotal->total_margem ?? 0) - ($summaryTotal->total_margem ?? 0));

        $hasErrors = $qtyDiff > $tolerance || $valueDiff > $tolerance || $margemDiff > $tolerance;

        // Exibe resultados
        if (!$hasErrors) {
            $this->info('✅ Todas as verificações passaram! Os dados estão corretos.');
            $this->table(
                ['Métrica', 'Sales', 'Monthly Summaries', 'Diferença', 'Status'],
                [
                    [
                        'Quantidade Total',
                        number_format($salesTotal->total_quantity ?? 0),
                        number_format($summaryTotal->total_quantity ?? 0),
                        number_format($qtyDiff, 2),
                        $qtyDiff <= $tolerance ? '✅' : '❌'
                    ],
                    [
                        'Valor Total',
                        'R$ ' . number_format($salesTotal->total_value ?? 0, 2, ',', '.'),
                        'R$ ' . number_format($summaryTotal->total_value ?? 0, 2, ',', '.'),
                        'R$ ' . number_format($valueDiff, 2, ',', '.'),
                        $valueDiff <= $tolerance ? '✅' : '❌'
                    ],
                    [
                        'Margem Total',
                        'R$ ' . number_format($salesTotal->total_margem ?? 0, 2, ',', '.'),
                        'R$ ' . number_format($summaryTotal->total_margem ?? 0, 2, ',', '.'),
                        'R$ ' . number_format($margemDiff, 2, ',', '.'),
                        $margemDiff <= $tolerance ? '✅' : '❌'
                    ],
                ]
            );
            return 0;
        }

        $this->error("❌ Diferenças encontradas nos totais:");
        $this->newLine();
        $this->warn("Diferença em Quantidade: " . number_format($qtyDiff, 2));
        $this->warn("Diferença em Valor: R$ " . number_format($valueDiff, 2, ',', '.'));
        $this->warn("Diferença em Margem: R$ " . number_format($margemDiff, 2, ',', '.'));
        $this->newLine();
        $this->table(
            ['Métrica', 'Sales', 'Monthly Summaries', 'Diferença'],
            [
                [
                    'Quantidade Total',
                    number_format($salesTotal->total_quantity ?? 0),
                    number_format($summaryTotal->total_quantity ?? 0),
                    number_format($qtyDiff, 2)
                ],
                [
                    'Valor Total',
                    'R$ ' . number_format($salesTotal->total_value ?? 0, 2, ',', '.'),
                    'R$ ' . number_format($summaryTotal->total_value ?? 0, 2, ',', '.'),
                    'R$ ' . number_format($valueDiff, 2, ',', '.')
                ],
                [
                    'Margem Total',
                    'R$ ' . number_format($salesTotal->total_margem ?? 0, 2, ',', '.'),
                    'R$ ' . number_format($summaryTotal->total_margem ?? 0, 2, ',', '.'),
                    'R$ ' . number_format($margemDiff, 2, ',', '.')
                ],
            ]
        );

        return 1;
    }
}
