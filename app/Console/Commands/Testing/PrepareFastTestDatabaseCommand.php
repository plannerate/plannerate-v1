<?php

namespace App\Console\Commands\Testing;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class PrepareFastTestDatabaseCommand extends Command
{
    protected $signature = 'test:fast-db:prepare';

    protected $description = 'Garante templates SQLite pré-migrados em storage/testing/ e copia pra arquivos de trabalho, pulando migrate:fresh quando as migrations não mudaram';

    private const TEMPLATE_DEFAULT = 'template-default.sqlite';

    private const TEMPLATE_TENANT = 'template-tenant.sqlite';

    private const TEMPLATE_LANDLORD = 'template-landlord.sqlite';

    private const WORK_DEFAULT = 'work-default.sqlite';

    private const WORK_TENANT = 'work-tenant.sqlite';

    private const WORK_LANDLORD = 'work-landlord.sqlite';

    private const HASH_FILE = '.migrations-hash';

    public function handle(): int
    {
        if (! app()->environment('testing')) {
            $this->error('test:fast-db:prepare só pode rodar com APP_ENV=testing.');

            return self::FAILURE;
        }

        $testingPath = storage_path('testing');
        File::ensureDirectoryExists($testingPath);

        $currentHash = $this->migrationsHash();
        $hashFile = "{$testingPath}/".self::HASH_FILE;
        $storedHash = File::exists($hashFile) ? File::get($hashFile) : null;

        $templatesExist = File::exists("{$testingPath}/".self::TEMPLATE_DEFAULT)
            && File::exists("{$testingPath}/".self::TEMPLATE_TENANT)
            && File::exists("{$testingPath}/".self::TEMPLATE_LANDLORD);

        if ($storedHash === $currentHash && $templatesExist) {
            $this->info('Templates já atualizados — pulando migrate:fresh.');
        } else {
            $this->rebuildTemplates($testingPath);
            File::put($hashFile, $currentHash);
            $this->info('Templates reconstruídos.');
        }

        $this->copyToWorkFiles($testingPath);
        $this->info('Arquivos de trabalho prontos.');

        return self::SUCCESS;
    }

    private function migrationsHash(): string
    {
        $files = collect(File::allFiles(database_path('migrations')))
            ->sortBy(fn ($file) => $file->getPathname())
            ->map(fn ($file) => $file->getPathname().':'.File::hash($file->getPathname()))
            ->implode('|');

        return sha1($files);
    }

    private function rebuildTemplates(string $testingPath): void
    {
        // migrate:fresh sem --database usa a conexão default ('sqlite') pro
        // bookkeeping, mas migrations com $connection='tenant' (a maioria)
        // roteiam seu DDL pra conexão 'tenant' — por isso as duas precisam
        // apontar pro template certo antes de rodar.
        $this->pointConnectionAt('sqlite', "{$testingPath}/".self::TEMPLATE_DEFAULT);
        $this->pointConnectionAt('tenant', "{$testingPath}/".self::TEMPLATE_TENANT);

        Artisan::call('migrate:fresh', [
            '--force' => true,
            '--no-interaction' => true,
        ]);

        $this->pointConnectionAt('landlord', "{$testingPath}/".self::TEMPLATE_LANDLORD);

        Artisan::call('migrate:fresh', [
            '--database' => 'landlord',
            '--path' => 'database/migrations/landlord',
            '--force' => true,
            '--no-interaction' => true,
        ]);
    }

    private function pointConnectionAt(string $connection, string $databasePath): void
    {
        // migrate:fresh só dropa tabelas na conexão que ele mesmo resolve
        // (a default, ou a passada em --database) — não nas conexões que
        // cada migration aponta via $connection. Sem recriar o arquivo do
        // zero aqui, um rebuild bateria em "table already exists" pro
        // schema antigo do template. O conector SQLite também recusa
        // conectar se o arquivo não existir, então precisa tocar de novo.
        File::put($databasePath, '');

        Config::set("database.connections.{$connection}.database", $databasePath);
        DB::purge($connection);
    }

    private function copyToWorkFiles(string $testingPath): void
    {
        File::copy("{$testingPath}/".self::TEMPLATE_DEFAULT, "{$testingPath}/".self::WORK_DEFAULT);
        File::copy("{$testingPath}/".self::TEMPLATE_TENANT, "{$testingPath}/".self::WORK_TENANT);
        File::copy("{$testingPath}/".self::TEMPLATE_LANDLORD, "{$testingPath}/".self::WORK_LANDLORD);
    }
}
