<?php

namespace Callcocam\LaravelRaptorPlannerate\Commands\Plannerate;

use Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\AutoGenerate\AutoGenerateConfigDTO;
use Callcocam\LaravelRaptorPlannerate\Services\Plannerate\AutoGenerate\AutoPlanogramService;
use Illuminate\Console\Command;

class TestAutoGenerateCommand extends Command
{
    protected $signature = 'plannerate:test-auto-generate
                            {planogram_id? : ID do planograma (ULID)}
                            {gondola_id? : ID da gondola (ULID) - opcional}
                            {--strategy=abc : Estrategia (abc, sales, margin, mix)}
                            {--use-existing : Usar analise ABC existente}
                            {--min-facings=1 : Facings minimo}
                            {--max-facings=10 : Facings maximo}';

    protected $description = 'Testa geracao automatica de planograma com IDs especificos';

    public function handle(AutoPlanogramService $service): int
    {
        $this->info('Iniciando teste de geracao automatica...');
        $this->newLine();

        $planogramId = $this->argument('planogram_id') ?? '01kfc8bdwhgxbx0w1gcvzkf7k9';
        $gondolaId = $this->argument('gondola_id') ?? '01kfc8bwf371cbygwe0nma6mt7';

        $this->info("Planograma ID: {$planogramId}");
        $this->info("Gondola ID: {$gondolaId}");
        $this->newLine();

        $config = new AutoGenerateConfigDTO(
            strategy: $this->option('strategy'),
            useExistingAnalysis: $this->option('use-existing'),
            startDate: now()->subYear()->toDateString(),
            endDate: now()->toDateString(),
            minFacings: (int) $this->option('min-facings'),
            maxFacings: (int) $this->option('max-facings'),
            groupBySubcategory: true,
            includeProductsWithoutSales: true,
            tableType: 'monthly_summaries',
        );

        $this->table(
            ['Configuracao', 'Valor'],
            [
                ['Estrategia', $config->strategy],
                ['Usar ABC existente', $config->useExistingAnalysis ? 'Sim' : 'Nao'],
                ['Data Inicio', $config->startDate ?? 'N/A'],
                ['Data Fim', $config->endDate ?? 'N/A'],
                ['Min Facings', $config->minFacings],
                ['Max Facings', $config->maxFacings],
                ['Agrupar por subcategoria', $config->groupBySubcategory ? 'Sim' : 'Nao'],
                ['Incluir sem vendas', $config->includeProductsWithoutSales ? 'Sim' : 'Nao'],
            ]
        );

        $this->newLine();

        try {
            $this->info('Gerando planograma...');
            $progressBar = $this->output->createProgressBar(5);
            $progressBar->start();

            $progressBar->advance();
            $progressBar->advance();

            $result = $service->generate($gondolaId, $config);

            $progressBar->advance();
            $progressBar->advance();
            $progressBar->advance();
            $progressBar->finish();

            $this->newLine(2);
            $this->info('Geracao concluida com sucesso!');
            $this->newLine();

            $this->table(
                ['Metrica', 'Valor'],
                [
                    ['Produtos Alocados', $result->totalAllocated],
                    ['Produtos Nao Alocados', $result->totalUnallocated],
                    ['Prateleiras Usadas', count($result->shelves)],
                    ['Gerado em', $result->generatedAt],
                ]
            );

            if (count($result->shelves) > 0) {
                $this->newLine();
                $this->info('Detalhes das Prateleiras:');
                $this->newLine();

                $shelfData = [];
                foreach ($result->shelves as $shelf) {
                    $shelfData[] = [
                        "Prateleira {$shelf->shelfIndex}",
                        count($shelf->products),
                        number_format($shelf->getOccupancyPercentage(), 1).'%',
                        number_format($shelf->availableWidth, 0).' cm',
                    ];
                }

                $this->table(
                    ['Prateleira', 'Produtos', 'Ocupacao', 'Largura'],
                    $shelfData
                );
            }

            if ($result->totalUnallocated > 0) {
                $this->newLine();
                $this->warn("Aviso: {$result->totalUnallocated} produtos nao couberam no planograma");

                if (count($result->unallocatedProducts) <= 10) {
                    $this->info('Produtos nao alocados:');
                    foreach ($result->unallocatedProducts as $product) {
                        $this->line("  - {$product->product->name} (ABC: {$product->abcClass})");
                    }
                }
            }

            $this->newLine();

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->newLine(2);
            $this->error('Erro ao gerar planograma:');
            $this->error($e->getMessage());
            $this->newLine();

            if ($this->output->isVerbose()) {
                $this->error($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }
}
