<?php

use Callcocam\LaravelRaptorTrade\Support\Database\TradeSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trilha de auditoria da atividade: quem mudou o quê e quando. Só grava
     * `created_at` (o registro é imutável). Preenchida pelo ActivityAuditLogger.
     */
    public function up(): void
    {
        Schema::create('trade_activity_audits', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            TradeSchema::owner($table)->nullable()->index();

            $table->foreignUlid('activity_id')->index();
            TradeSchema::reference($table, 'user_id', 'user')->nullable()->index();

            $table->string('event', 30);
            $table->string('field_name')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('created_at')->nullable()->index();

            $table->index(['activity_id', 'created_at']);
            $table->index([TradeSchema::ownerColumn(), 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_activity_audits');
    }
};
