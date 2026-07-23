<?php

use Callcocam\LaravelRaptorTrade\Support\Database\TradeSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Assinaturas de push do PWA de campo (Fase 8).
     *
     * Espelha a tabela do `laravel-notification-channels/webpush`, mas com o
     * prefixo `trade_` (D2) e o `subscribable_id` no tipo de chave do usuário
     * do host (ULID no plannerate). O `id` continua auto-incremento porque o
     * model base do webpush não usa ULID e ninguém referencia essa chave.
     */
    public function up(): void
    {
        Schema::create('trade_push_subscriptions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('subscribable_type');
            TradeSchema::reference($table, 'subscribable_id', 'user');
            $table->index(['subscribable_type', 'subscribable_id'], 'trade_push_subscriptions_subscribable_index');
            $table->string('endpoint', 500)->unique();
            $table->string('public_key')->nullable();
            $table->string('auth_token')->nullable();
            $table->string('content_encoding')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_push_subscriptions');
    }
};
