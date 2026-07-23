<?php

use Callcocam\LaravelRaptorTrade\Support\Database\TradeSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vínculo usuário ↔ loja (D7). Substitui o `loja_user` da origem: quem
     * executa atividades em campo por uma loja. Base de
     * `TradeUserContext::isStoreExecutor()`.
     */
    public function up(): void
    {
        Schema::create('trade_store_user', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            TradeSchema::owner($table)->nullable()->index();
            TradeSchema::reference($table, 'store_id', 'store')->index();
            TradeSchema::reference($table, 'user_id', 'user')->index();
            $table->timestamps();

            $table->unique([TradeSchema::ownerColumn(), 'store_id', 'user_id'], 'trade_store_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_store_user');
    }
};
