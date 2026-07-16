<?php

namespace App\Console\Commands\Integrations;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Remove arquivos temporários do pipeline de importação que ficaram para trás:
 * - órfãos em imports/ — o FetchIntegrationPageJob gravou o JSON mas o
 *   ProcessPageResponseJob nunca consumiu (ex.: worker morto entre o put e o dispatch)
 * - quarentena em imports/failed/ — páginas cujo processamento falhou, mantidas
 *   por uns dias para diagnóstico/reprocesso manual
 */
class PruneImportFilesCommand extends Command
{
    protected $signature = 'imports:prune
        {--hours=48 : Idade mínima em horas para remover órfãos de imports/}
        {--failed-days=7 : Idade mínima em dias para remover arquivos da quarentena imports/failed/}';

    protected $description = 'Remove arquivos temporários órfãos e de quarentena do pipeline de importação';

    public function handle(): int
    {
        $orphanHours = max(1, (int) $this->option('hours'));
        $failedDays = max(1, (int) $this->option('failed-days'));

        $orphansRemoved = $this->pruneDirectory('imports', now()->subHours($orphanHours)->getTimestamp());
        $quarantineRemoved = $this->pruneDirectory('imports/failed', now()->subDays($failedDays)->getTimestamp());

        $this->info(sprintf(
            'Removidos: %d órfão(s) de imports/ (>%dh), %d arquivo(s) da quarentena (>%dd).',
            $orphansRemoved,
            $orphanHours,
            $quarantineRemoved,
            $failedDays,
        ));

        if ($orphansRemoved > 0 || $quarantineRemoved > 0) {
            Log::info('imports:prune removeu arquivos temporários de importação', [
                'orphans_removed' => $orphansRemoved,
                'quarantine_removed' => $quarantineRemoved,
            ]);
        }

        return self::SUCCESS;
    }

    /** files() não é recursivo: varrer imports/ não toca imports/failed/. */
    private function pruneDirectory(string $directory, int $olderThanTimestamp): int
    {
        $disk = Storage::disk('local');
        $removed = 0;

        foreach ($disk->files($directory) as $file) {
            if ($disk->lastModified($file) < $olderThanTimestamp) {
                $disk->delete($file);
                $removed++;
            }
        }

        return $removed;
    }
}
