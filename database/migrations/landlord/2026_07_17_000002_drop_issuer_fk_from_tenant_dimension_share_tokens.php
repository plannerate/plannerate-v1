<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'landlord';

    /**
     * Remove a FK issuer_id → users em bancos já migrados. O emissor é um usuário
     * do tenant (conexão tenant), então não pode referenciar users do landlord.
     */
    public function up(): void
    {
        if (DB::connection($this->connection)->getDriverName() !== 'pgsql') {
            return;
        }

        DB::connection($this->connection)->statement(
            'ALTER TABLE tenant_dimension_share_tokens DROP CONSTRAINT IF EXISTS tenant_dimension_share_tokens_issuer_id_foreign'
        );
    }

    public function down(): void
    {
        // Não recriamos a FK: ela era incorreta (cross-connection).
    }
};
