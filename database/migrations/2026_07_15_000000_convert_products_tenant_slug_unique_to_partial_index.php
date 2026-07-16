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
     * Converte a constraint UNIQUE completa de slug em índice único parcial
     * (WHERE deleted_at IS NULL), espelhando o índice de EAN.
     *
     * Sem isso há uma assimetria: o índice de EAN ignora soft-deleted, mas o de
     * slug não — um produto soft-deleted continua bloqueando a reimportação de
     * outro produto com o mesmo slug (o reconciler realinha por EAN, não por
     * slug), estourando `duplicate key ... products_tenant_id_slug_unique`.
     *
     * Converter só RELAXA a constraint: se a unicidade completa valia, a
     * unicidade entre ativos continua valendo — a migration não pode falhar
     * por dados existentes.
     */
    public function up(): void
    {
        // SQLite (usado nos testes) segue o mesmo padrão da conversão do EAN.
        if (DB::connection($this->connection)->getDriverName() !== 'pgsql') {
            return;
        }

        DB::connection($this->connection)->statement(
            'ALTER TABLE products DROP CONSTRAINT IF EXISTS products_tenant_id_slug_unique'
        );

        DB::connection($this->connection)->statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS products_tenant_id_slug_unique
             ON products (tenant_id, slug)
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
            'DROP INDEX IF EXISTS products_tenant_id_slug_unique'
        );

        DB::connection($this->connection)->statement(
            'ALTER TABLE products ADD CONSTRAINT products_tenant_id_slug_unique UNIQUE (tenant_id, slug)'
        );
    }
};
