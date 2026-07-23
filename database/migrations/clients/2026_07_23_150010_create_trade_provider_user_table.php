<?php

use Callcocam\LaravelRaptorTrade\Support\Database\TradeSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vínculo usuário ↔ fornecedor (D7). Substitui o `parceiro_user` da origem:
     * um usuário do portal pode responder por um ou mais fornecedores, e um
     * fornecedor pode ter vários usuários (vendedores). É a base de
     * `TradeUserContext::isSupplier()` e do escopo do portal.
     *
     * PK ULID + coluna de escopo → precisa de model Pivot com `->using()`
     * (ver a armadilha do `ReservationStoreMap`).
     */
    public function up(): void
    {
        Schema::create('trade_provider_user', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            TradeSchema::owner($table)->nullable()->index();
            TradeSchema::reference($table, 'provider_id', 'provider')->index();
            TradeSchema::reference($table, 'user_id', 'user')->index();
            $table->string('role', 30)->nullable();
            $table->timestamps();

            $table->unique([TradeSchema::ownerColumn(), 'provider_id', 'user_id'], 'trade_provider_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_provider_user');
    }
};
