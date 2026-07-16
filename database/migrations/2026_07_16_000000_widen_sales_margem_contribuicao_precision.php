<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Conexão tenant — executar via:
     * docker compose exec php php artisan tenants:artisan "migrate --database=tenant"
     */
    protected $connection = 'tenant';

    /**
     * Amplia margem_contribuicao de numeric(15,2) para numeric(15,4).
     *
     * A margem é calculada no import pela expressão
     * `valor_liquido - valor_impostos - custo_medio_loja` em precisão cheia
     * (ex.: 5.2493), mas a coluna com 2 casas arredondava cada linha (5.25).
     * Somar 32 linhas arredondadas dava 242.13 em vez de 242.0805 (soma em
     * precisão cheia — o número do ERP/planilha). Com 4 casas, a precisão da
     * expressão é preservada e a agregação bate com o ERP.
     *
     * As linhas já gravadas continuam arredondadas até serem reimportadas
     * (integration:backfill --path=sales), quando o upsert por id atualiza a
     * coluna com o valor recalculado.
     */
    public function up(): void
    {
        // SQLite (testes) não tem precisão fixa em decimal — nada a fazer.
        if (DB::connection($this->connection)->getDriverName() !== 'pgsql') {
            return;
        }

        DB::connection($this->connection)->statement(
            'ALTER TABLE sales ALTER COLUMN margem_contribuicao TYPE numeric(15,4)'
        );
    }

    public function down(): void
    {
        if (DB::connection($this->connection)->getDriverName() !== 'pgsql') {
            return;
        }

        DB::connection($this->connection)->statement(
            'ALTER TABLE sales ALTER COLUMN margem_contribuicao TYPE numeric(15,2)'
        );
    }
};
