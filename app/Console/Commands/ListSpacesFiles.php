<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ListSpacesFiles extends Command
{
    protected $signature = 'spaces:list-files
                            {--disk=spaces        : Nome do disk configurado no filesystems.php}
                            {--prefix=            : Prefixo/pasta para filtrar (ex: planogramas/)}
                            {--output=table       : Formato de saída: table | csv | json}
                            {--save               : Salvar relatório em storage/app/spaces-report.csv}';

    protected $description = 'Lista todos os arquivos do DigitalOcean Spaces com nome e tamanho';

    public function handle(): int
    {
        $disk = $this->option('disk');
        $prefix = $this->option('prefix') ?? '';
        $output = $this->option('output');
        $save = $this->option('save');

        $this->info("🔍 Conectando ao disk [{$disk}]...");

        try {
            $storage = Storage::disk($disk);
            $files = $storage->allFiles($prefix);
        } catch (\Exception $e) {
            $this->error('❌ Erro ao conectar: '.$e->getMessage());
            $this->line('');
            $this->line('Verifique as variáveis no .env:');
            $this->line('  DO_SPACES_KEY, DO_SPACES_SECRET, DO_SPACES_REGION,');
            $this->line('  DO_SPACES_BUCKET, DO_SPACES_ENDPOINT, DO_SPACES_URL');

            return self::FAILURE;
        }

        if (empty($files)) {
            $this->warn('⚠️  Nenhum arquivo encontrado'.($prefix ? " no prefixo [{$prefix}]" : '').'.');

            return self::SUCCESS;
        }

        // ── Montar dados ──────────────────────────────────────────────────────
        $rows = [];
        $totalBytes = 0;
        $totalFiles = 0;

        $this->info('📂 Lendo metadados de '.count($files).' arquivo(s)...');
        $bar = $this->output->createProgressBar(count($files));
        $bar->start();

        foreach ($files as $file) {
            try {
                $size = $storage->size($file);
            } catch (\Exception) {
                $size = 0;
            }

            $totalBytes += $size;
            $totalFiles++;

            $rows[] = [
                'arquivo' => $file,
                'tamanho' => $size,
                'legivel' => $this->formatBytes($size),
            ];

            $bar->advance();
        }

        $bar->finish();
        $this->line('');
        $this->line('');

        // ── Exibir ────────────────────────────────────────────────────────────
        match ($output) {
            'json' => $this->outputJson($rows),
            'csv' => $this->outputCsv($rows),
            default => $this->outputTable($rows),
        };

        // ── Resumo ────────────────────────────────────────────────────────────
        $this->line('');
        $this->info("📊 Total de arquivos : {$totalFiles}");
        $this->info('💾 Tamanho total     : '.$this->formatBytes($totalBytes));

        // ── Salvar CSV ────────────────────────────────────────────────────────
        if ($save) {
            $this->saveCsv($rows, $totalFiles, $totalBytes);
        }

        return self::SUCCESS;
    }

    // ── Helpers de saída ──────────────────────────────────────────────────────

    private function outputTable(array $rows): void
    {
        $this->table(
            ['#', 'Arquivo', 'Tamanho'],
            array_map(
                fn ($i, $r) => [$i + 1, $r['arquivo'], $r['legivel']],
                array_keys($rows),
                $rows
            )
        );
    }

    private function outputJson(array $rows): void
    {
        $this->line(json_encode(
            array_map(fn ($r) => ['arquivo' => $r['arquivo'], 'tamanho_bytes' => $r['tamanho']], $rows),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));
    }

    private function outputCsv(array $rows): void
    {
        $this->line('arquivo,tamanho_bytes,tamanho_legivel');
        foreach ($rows as $r) {
            $this->line("{$r['arquivo']},{$r['tamanho']},{$r['legivel']}");
        }
    }

    private function saveCsv(array $rows, int $totalFiles, int $totalBytes): void
    {
        $path = 'spaces-report.csv';
        $content = "arquivo,tamanho_bytes,tamanho_legivel\n";

        foreach ($rows as $r) {
            $content .= "{$r['arquivo']},{$r['tamanho']},{$r['legivel']}\n";
        }

        $content .= "\nTotal de arquivos,{$totalFiles}\n";
        $content .= "Tamanho total (bytes),{$totalBytes}\n";

        Storage::disk('local')->put($path, $content);
        $this->info("💾 Relatório salvo em storage/app/{$path}");
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1_073_741_824) {
            return round($bytes / 1_073_741_824, 2).' GB';
        }
        if ($bytes >= 1_048_576) {
            return round($bytes / 1_048_576, 2).' MB';
        }
        if ($bytes >= 1_024) {
            return round($bytes / 1_024, 2).' KB';
        }

        return $bytes.' B';
    }
}
