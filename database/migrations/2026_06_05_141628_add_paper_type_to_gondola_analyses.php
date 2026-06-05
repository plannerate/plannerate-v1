<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'tenant';

    /**
     * Adiciona 'paper' aos tipos válidos da Análise de Papel em gondola_analyses.
     * O enum no PostgreSQL é implementado como CHECK constraint — removemos a antiga e criamos uma nova.
     */
    public function up(): void
    {
        if (DB::connection($this->connection)->getDriverName() !== 'pgsql') {
            return;
        }

        DB::connection($this->connection)->statement(
            'ALTER TABLE gondola_analyses DROP CONSTRAINT IF EXISTS gondola_analyses_type_check'
        );

        DB::connection($this->connection)->statement(
            "ALTER TABLE gondola_analyses ADD CONSTRAINT gondola_analyses_type_check
             CHECK (type IN ('abc', 'stock', 'bcg', 'paper'))"
        );
    }

    public function down(): void
    {
        if (DB::connection($this->connection)->getDriverName() !== 'pgsql') {
            return;
        }

        DB::connection($this->connection)->statement(
            'ALTER TABLE gondola_analyses DROP CONSTRAINT IF EXISTS gondola_analyses_type_check'
        );

        DB::connection($this->connection)->statement(
            "ALTER TABLE gondola_analyses ADD CONSTRAINT gondola_analyses_type_check
             CHECK (type IN ('abc', 'stock', 'bcg'))"
        );
    }
};
