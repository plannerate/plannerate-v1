<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Conexão tenant — esta migration deve ser executada via:
     * docker compose exec php php artisan tenants:artisan "migrate --database=tenant"
     */
    protected $connection = 'tenant';

    /**
     * Converte a constraint UNIQUE completa em índice único parcial (WHERE deleted_at IS NULL).
     *
     * Sem isso, produtos soft-deleted continuam bloqueando a reimportação do mesmo EAN —
     * a constraint normal não ignora registros deletados, então o upsert falha ao tentar
     * inserir um produto com EAN já existente (mesmo que esteja soft-deleted).
     */
    public function up(): void
    {
        // SQLite (usado nos testes) não suporta índices parciais com WHERE.
        if (DB::connection($this->connection)->getDriverName() !== 'pgsql') {
            return;
        }

        // Remove a constraint UNIQUE completa gerada pela migration original.
        DB::connection($this->connection)->statement(
            'ALTER TABLE products DROP CONSTRAINT IF EXISTS products_tenant_id_ean_unique'
        );

        // Cria índice único parcial: apenas registros ativos (não soft-deleted)
        // competem pela unicidade de tenant_id + ean.
        DB::connection($this->connection)->statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS products_tenant_id_ean_unique
             ON products (tenant_id, ean)
             WHERE deleted_at IS NULL'
        );
    }

    /**
     * Restaura a constraint UNIQUE completa original.
     */
    public function down(): void
    {
        if (DB::connection($this->connection)->getDriverName() !== 'pgsql') {
            return;
        }

        DB::connection($this->connection)->statement(
            'DROP INDEX IF EXISTS products_tenant_id_ean_unique'
        );

        DB::connection($this->connection)->statement(
            'ALTER TABLE products ADD CONSTRAINT products_tenant_id_ean_unique UNIQUE (tenant_id, ean)'
        );
    }
};
