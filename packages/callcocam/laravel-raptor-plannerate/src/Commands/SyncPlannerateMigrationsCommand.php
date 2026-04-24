<?php

namespace Callcocam\LaravelRaptorPlannerate\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class SyncPlannerateMigrationsCommand extends Command
{
    protected $signature = 'plannerate:migrations:sync
                            {--force : Sobrescreve arquivos já existentes no destino}
                            {--dry-run : Mostra o que seria copiado sem gravar arquivos}
                            {--target= : Caminho de destino (absoluto ou relativo ao projeto)}';

    protected $description = 'Sincroniza migrations do pacote Plannerate para database/migrations/clients';

    public function handle(Filesystem $files): int
    {
        $source = dirname(__DIR__, 2).'/database/migrations/clients';

        if (! $files->isDirectory($source)) {
            $this->error("Diretório de origem não encontrado: {$source}");

            return self::FAILURE;
        }

        $targetOption = (string) ($this->option('target') ?? '');
        $target = $targetOption !== ''
            ? $this->resolveTargetPath($targetOption)
            : base_path(config('plannerate.package.migrations.client_path', 'database/migrations/clients'));

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $this->line("Origem: {$source}");
        $this->line("Destino: {$target}");

        if (! $dryRun && ! $files->isDirectory($target)) {
            $files->makeDirectory($target, 0755, true);
        }

        $copied = 0;
        $overwritten = 0;
        $skipped = 0;

        $migrationFiles = collect($files->files($source))
            ->sortBy(fn ($file) => $file->getFilename())
            ->values();

        if ($migrationFiles->isEmpty()) {
            $this->warn('Nenhuma migration encontrada para sincronizar.');

            return self::SUCCESS;
        }

        foreach ($migrationFiles as $file) {
            $destination = rtrim($target, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$file->getFilename();
            $exists = $files->exists($destination);

            if ($exists && ! $force) {
                $this->line("SKIP {$file->getFilename()}");
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $action = $exists ? 'OVERWRITE' : 'COPY';
                $this->line("{$action} {$file->getFilename()}");

                if ($exists) {
                    $overwritten++;
                } else {
                    $copied++;
                }

                continue;
            }

            $files->copy($file->getPathname(), $destination);
            $action = $exists ? 'OVERWRITE' : 'COPY';
            $this->info("{$action} {$file->getFilename()}");

            if ($exists) {
                $overwritten++;
            } else {
                $copied++;
            }
        }

        $this->newLine();
        $this->table(['Ação', 'Quantidade'], [
            ['Copiados', $copied],
            ['Sobrescritos', $overwritten],
            ['Ignorados', $skipped],
        ]);

        if ($dryRun) {
            $this->comment('Dry-run: nenhum arquivo foi alterado.');
        }

        return self::SUCCESS;
    }

    protected function resolveTargetPath(string $target): string
    {
        if (str_starts_with($target, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $target)) {
            return $target;
        }

        return base_path($target);
    }
}
