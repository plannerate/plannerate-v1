<?php

namespace App\Console\Commands\Logs;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('logs:archive-and-clear {--days=5 : Remove arquivos arquivados com mais de X dias} {--path= : Diretório de logs (opcional)}')]
#[Description('Cria cópia diária do laravel.log, limpa o arquivo atual e remove arquivos arquivados antigos')]
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

        $mainLogPath = $basePath.DIRECTORY_SEPARATOR.'laravel.log';
        if (! is_file($mainLogPath)) {
            file_put_contents($mainLogPath, '');
        }

        $archivePath = $basePath.DIRECTORY_SEPARATOR.'laravel-'.Carbon::now()->format('Y-m-d_His').'.log';

        if (! @copy($mainLogPath, $archivePath)) {
            $this->error(sprintf('Falha ao criar cópia de segurança de %s', $mainLogPath));

            return self::FAILURE;
        }

        file_put_contents($mainLogPath, '');

        $threshold = Carbon::now()->subDays($days);
        $deleted = 0;
        $checked = 0;

        foreach (glob($basePath.DIRECTORY_SEPARATOR.'laravel-*.log') ?: [] as $file) {
            if (! is_string($file) || ! is_file($file)) {
                continue;
            }

            $checked++;
            $lastModified = Carbon::createFromTimestamp((int) filemtime($file));

            if ($lastModified->gt($threshold)) {
                continue;
            }

            if (realpath($file) === realpath($archivePath)) {
                continue;
            }

            if (@unlink($file)) {
                $deleted++;
            }
        }

        $this->info(sprintf(
            'Arquivo laravel.log arquivado em %s e limpo. Arquivos arquivados verificados: %d. Arquivos antigos removidos: %d.',
            basename($archivePath),
            $checked,
            $deleted,
        ));

        return self::SUCCESS;
    }
}
