<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateMissingImagesReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'report:missing-images {--clear : Limpa o relatório após gerar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gera relatório consolidado de imagens não encontradas';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $reportPath = 'reports/missing-images.txt';

        if (! Storage::disk('local')->exists($reportPath)) {
            $this->warn('Nenhum relatório de imagens não encontradas.');

            return self::SUCCESS;
        }

        $content = Storage::disk('local')->get($reportPath);
        $lines = array_filter(explode("\n", $content));

        if (empty($lines)) {
            $this->warn('Relatório vazio.');

            return self::SUCCESS;
        }

        // Extrai EANs únicos
        $eans = [];
        foreach ($lines as $line) {
            if (preg_match('/EAN:\s*(\d+)/', $line, $matches)) {
                $eans[$matches[1]] = ($eans[$matches[1]] ?? 0) + 1;
            }
        }

        // Ordena por quantidade de ocorrências (desc)
        arsort($eans);

        $this->info('Relatório de Imagens Não Encontradas');
        $this->info('======================================');
        $this->newLine();
        $this->info('Total de tentativas: '.count($lines));
        $this->info('EANs únicos: '.count($eans));
        $this->newLine();

        $this->table(
            ['EAN', 'Tentativas'],
            collect($eans)->take(50)->map(fn ($count, $ean) => [$ean, $count])->toArray()
        );

        // Gera arquivo CSV para análise
        $csvPath = 'reports/missing-images-'.now()->format('Y-m-d_His').'.csv';
        $csvContent = "EAN,Tentativas\n";
        foreach ($eans as $ean => $count) {
            $csvContent .= "{$ean},{$count}\n";
        }
        Storage::disk('local')->put($csvPath, $csvContent);

        $fullPath = Storage::disk('local')->path($csvPath);
        $this->info("Relatório CSV gerado: {$fullPath}");

        if ($this->option('clear')) {
            Storage::disk('local')->delete($reportPath);
            $this->info('Relatório limpo.');
        }

        return self::SUCCESS;
    }
}
