<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ReadExcelFileCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'excel:read {file : Caminho do arquivo Excel} {--limit=50 : Limite de linhas a exibir}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lê e exibe o conteúdo de um arquivo Excel';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->argument('file');
        $limit = (int) $this->option('limit');

        // Verifica se o arquivo existe
        if (! Storage::disk('public')->exists($filePath)) {
            $this->error("❌ Arquivo não encontrado: {$filePath}");

            return self::FAILURE;
        }

        $fullPath = Storage::disk('public')->path($filePath);

        try {
            // Carrega o arquivo Excel
            $spreadsheet = IOFactory::load($fullPath);
            $worksheet = $spreadsheet->getActiveSheet();

            $this->info("📊 Lendo arquivo: {$filePath}");
            $this->info("📋 Planilha: {$worksheet->getTitle()}");
            $this->line('');

            // Obtém os dados
            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestColumn();
            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

            $this->info("Total de linhas: {$highestRow}");
            $this->info("Total de colunas: {$highestColumnIndex}");
            $this->line('');

            // Lê o cabeçalho (primeira linha)
            $headers = [];
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $cellValue = $worksheet->getCell($columnLetter . '1')->getValue();
                $headers[] = $cellValue ?? '';
            }

            $this->info('📋 Cabeçalhos:');
            $this->table($headers, [[]]);
            $this->line('');

            // Lê as linhas de dados
            $data = [];
            $rowsToRead = min($limit, $highestRow - 1); // -1 porque a primeira linha é cabeçalho

            for ($row = 2; $row <= min($rowsToRead + 1, $highestRow); $row++) {
                $rowData = [];
                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                    $cellValue = $worksheet->getCell($columnLetter . $row)->getValue();
                    $rowData[] = $cellValue ?? '';
                }
                $data[] = $rowData;
            }

            $this->info("📊 Primeiras {$rowsToRead} linhas de dados:");
            $this->table($headers, $data);

            if ($highestRow - 1 > $limit) {
                $this->warn("⚠️  Mostrando apenas {$limit} de " . ($highestRow - 1) . ' linhas. Use --limit para ver mais.');
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Erro ao ler arquivo: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
