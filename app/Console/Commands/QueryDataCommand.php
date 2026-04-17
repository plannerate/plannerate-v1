<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class QueryDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:query 
                            {database : Nome do banco de dados}
                            {table : Nome da tabela}
                            {id? : ID do registro (opcional)}
                            {--limit=10 : Limite de resultados quando ID não for especificado}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consulta dados em qualquer banco/tabela';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $database = $this->argument('database');
        $table = $this->argument('table');
        $id = $this->argument('id');
        $limit = $this->option('limit');

        $this->info("🔍 Consultando: {$database}.{$table}".($id ? " (ID: {$id})" : ''));
        $this->newLine();

        try {
            // Clonar config da conexão default
            $defaultConnection = config('database.default');
            $defaultConfig = config("database.connections.{$defaultConnection}");

            // Criar nova configuração com o banco especificado
            $tempConnection = 'temp_query_'.uniqid();
            Config::set("database.connections.{$tempConnection}", array_merge($defaultConfig, [
                'database' => $database,
            ]));

            // Purge para forçar nova conexão
            DB::purge($tempConnection);

            // Executar query
            $query = DB::connection($tempConnection)->table($table);

            if ($id) {
                // Buscar registro específico
                $result = $query->find($id);

                if ($result) {
                    $this->info('✅ Registro encontrado:');
                    $this->newLine();
                    $this->table(['Campo', 'Valor'], collect($result)->map(function ($value, $key) {
                        return [$key, is_null($value) ? 'NULL' : (is_bool($value) ? ($value ? 'true' : 'false') : $value)];
                    })->toArray());
                } else {
                    $this->error('❌ Registro não encontrado!');

                    return self::FAILURE;
                }
            } else {
                // Listar múltiplos registros
                $results = $query->limit($limit)->get();

                if ($results->isEmpty()) {
                    $this->warn('⚠️  Nenhum registro encontrado!');

                    return self::SUCCESS;
                }

                $this->info("✅ {$results->count()} registro(s) encontrado(s):");
                $this->newLine();

                // Pegar as colunas do primeiro registro
                $columns = array_keys((array) $results->first());

                // Formatar dados para tabela
                $rows = $results->map(function ($row) {
                    return collect($row)->map(function ($value) {
                        if (is_null($value)) {
                            return 'NULL';
                        }
                        if (is_bool($value)) {
                            return $value ? 'true' : 'false';
                        }
                        if (is_string($value) && strlen($value) > 50) {
                            return substr($value, 0, 47).'...';
                        }

                        return $value;
                    })->toArray();
                })->toArray();

                $this->table($columns, $rows);

                if ($results->count() == $limit) {
                    $this->comment("💡 Mostrando primeiros {$limit} registros. Use --limit=N para mais.");
                }
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Erro: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
