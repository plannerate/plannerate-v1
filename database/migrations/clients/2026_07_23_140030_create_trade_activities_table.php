<?php

use Callcocam\LaravelRaptorTrade\Support\Database\TradeSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Atividade de campo: a execução na loja (montagem, verificação, auditoria,
     * foto). Consolida as ~16 migrations incrementais da origem.
     *
     * Dois eixos independentes de estado:
     *   status           → andamento (pendente → em_andamento → concluida/cancelada)
     *   approval_status  → aprovação do planejamento (pending/approved/rejected)
     *   proof_status     → comprovação por foto (null/submitted/approved/rejected)
     *
     * `tipo` referencia `trade_activity_types.slug` (não a PK). O link público de
     * execução vive em `share_token` (+ senha opcional) e é resolvido pelo
     * subdomínio do tenant, sem sessão.
     */
    public function up(): void
    {
        Schema::create('trade_activities', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            TradeSchema::owner($table)->nullable()->index();

            $table->string('titulo');
            $table->string('tipo', 50)->default('execucao');
            $table->string('target_type', 20)->default('loja');

            $table->text('motivo_nao_execucao')->nullable();
            $table->text('motivo_nao_conformidade')->nullable();

            $table->date('data_inicio')->nullable();
            $table->date('data_fim')->nullable();
            $table->timestamp('data_conclusao')->nullable();

            $table->string('status', 20)->default('pendente');
            $table->string('prioridade', 20)->default('media');

            $table->string('approval_status', 20)->default('pending');
            TradeSchema::reference($table, 'approved_by', 'user')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_comments')->nullable();

            TradeSchema::reference($table, 'responsavel_id', 'user')->nullable()->index();
            TradeSchema::reference($table, 'supplier_id', 'provider')->nullable();
            TradeSchema::reference($table, 'supplier_user_id', 'user')->nullable();
            TradeSchema::reference($table, 'store_id', 'store')->nullable()->index();

            $table->foreignUlid('reservation_id')->nullable()->index();
            $table->foreignUlid('contract_id')->nullable()->index();
            $table->foreignUlid('space_id')->nullable()->index();
            $table->ulid('current_step_id')->nullable()->index();

            $table->json('checklist')->nullable();
            $table->boolean('requires_photo_proof')->default(false);

            $table->string('proof_status', 20)->nullable();
            $table->text('proof_note')->nullable();
            TradeSchema::reference($table, 'proof_submitted_by', 'user')->nullable();
            $table->timestamp('proof_submitted_at')->nullable();
            TradeSchema::reference($table, 'proof_reviewed_by', 'user')->nullable();
            $table->timestamp('proof_reviewed_at')->nullable();
            $table->text('proof_rejection_reason')->nullable();

            $table->string('share_token', 80)->nullable()->unique();
            $table->string('share_password_hash')->nullable();

            $table->json('fotos')->nullable();
            $table->json('fotos_antes')->nullable();
            $table->text('observacoes_internas')->nullable();
            $table->text('comentarios_execucao')->nullable();
            $table->json('anexos')->nullable();

            $table->decimal('geo_lat', 10, 8)->nullable();
            $table->decimal('geo_lng', 11, 8)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index([TradeSchema::ownerColumn(), 'status']);
            $table->index([TradeSchema::ownerColumn(), 'approval_status']);
            $table->index([TradeSchema::ownerColumn(), 'proof_status']);
            $table->index(['status', 'data_inicio']);
            $table->index(['tipo', 'prioridade']);
            $table->index(['target_type', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_activities');
    }
};
