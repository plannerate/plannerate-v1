<?php

use Callcocam\LaravelRaptorTrade\Support\Database\TradeSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aprovação interna em quatro estágios (analista → gerente comercial →
     * financeiro → diretoria). As quatro linhas nascem com o contrato; a ordem
     * é a de `sort_order` e cada etapa só abre depois da anterior.
     */
    public function up(): void
    {
        Schema::create('trade_contract_internal_approvals', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            TradeSchema::owner($table)->nullable()->index();
            $table->foreignUlid('contract_id')->index();

            $table->unsignedTinyInteger('sort_order');
            $table->string('step', 20);
            $table->string('status', 20)->default('pending');
            TradeSchema::reference($table, 'approved_by', 'user')->nullable()->index();
            $table->timestamp('approved_at')->nullable();
            $table->text('comments')->nullable();

            $table->timestamps();

            $table->unique(['contract_id', 'step']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_contract_internal_approvals');
    }
};
