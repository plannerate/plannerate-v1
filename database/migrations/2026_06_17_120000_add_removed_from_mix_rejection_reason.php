<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adiciona o valor 'removed_from_mix' ao CHECK constraint de rejection_reason.
 *
 * Usado para produtos retirados do mix pela recomendação explícita do ABC
 * (retirar_do_mix) antes do placement — agora registrados na lista de rejeitados.
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
                 'manually_removed',
                 'removed_from_mix'
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
                 'missing_dimensions',
                 'blocked',
                 'mandatory_no_space',
                 'manually_removed'
             ))"
        );
    }
};
