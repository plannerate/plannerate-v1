<?php

namespace App\Console\Commands\Logs;

use Illuminate\Support\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('logs:clean-old {--days=5 : Remove logs older than this amount of days} {--path= : Custom logs directory (optional)}')]
#[Description('Remove arquivos de log antigos do sistema')]
class CleanOldLogsCommand extends Command
{
    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $basePath = is_string($this->option('path')) && trim((string) $this->option('path')) !== ''
            ? trim((string) $this->option('path'))
            : storage_path('logs');

        if (! is_dir($basePath)) {
            $this->warn(sprintf('Diretório de logs não encontrado: %s', $basePath));

            return self::SUCCESS;
        }

        $threshold = Carbon::now()->subDays($days);
        $deleted = 0;
        $checked = 0;

        foreach (glob($basePath.DIRECTORY_SEPARATOR.'*.log') ?: [] as $file) {
            if (! is_string($file) || ! is_file($file)) {
                continue;
            }

            $checked++;
            $lastModified = Carbon::createFromTimestamp((int) filemtime($file));

            if ($lastModified->gt($threshold)) {
                continue;
            }

            if (@unlink($file)) {
                $deleted++;
            }
        }

        $this->info(sprintf(
            'Limpeza de logs concluída. Arquivos verificados: %d. Arquivos removidos: %d.',
            $checked,
            $deleted,
        ));

        return self::SUCCESS;
    }
}
