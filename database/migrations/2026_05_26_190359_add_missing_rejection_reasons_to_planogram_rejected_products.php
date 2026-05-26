<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adiciona os valores faltantes ao CHECK constraint de rejection_reason.
 *
 * Valores anteriores: no_horizontal_space, height_exceeds_shelf, no_shelf_at_level, missing_dimensions
 * Valores adicionados: blocked, mandatory_no_space, manually_removed
 *
 * O padrão de recriar o constraint via SQL raw é necessário porque o Laravel não
 * suporta alteração de CHECK constraints em PostgreSQL via Blueprint diretamente.
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        // SQLite (testes) não suporta ALTER TABLE com CHECK constraints do PostgreSQL
        if (DB::connection($this->connection)->getDriverName() !== 'pgsql') {
            return;
        }

        DB::connection($this->connection)->statement(
            'ALTER TABLE planogram_rejected_products DROP CONSTRAINT IF EXISTS planogram_rejected_products_rejection_reason_check'
        );

        DB::connection($this->connection)->statement(
            "ALTER TABLE planogram_rejected_products ADD CONSTRAINT planogram_rejected_products_rejection_reason_check
             CHECK (rejection_reason IN (
                 'no_horizontal_space',
                 'height_exceeds_shelf',
                 'no_shelf_at_level',
                 'missing_dimensions',
                 'blocked',
                 'mandatory_no_space',
                 'manually_removed'
             ))"
        );
    }

    public function down(): void
    {
        if (DB::connection($this->connection)->getDriverName() !== 'pgsql') {
            return;
        }

        DB::connection($this->connection)->statement(
            'ALTER TABLE planogram_rejected_products DROP CONSTRAINT IF EXISTS planogram_rejected_products_rejection_reason_check'
        );

        DB::connection($this->connection)->statement(
            "ALTER TABLE planogram_rejected_products ADD CONSTRAINT planogram_rejected_products_rejection_reason_check
             CHECK (rejection_reason IN (
                 'no_horizontal_space',
                 'height_exceeds_shelf',
                 'no_shelf_at_level',
                 'missing_dimensions'
             ))"
        );
    }
};
