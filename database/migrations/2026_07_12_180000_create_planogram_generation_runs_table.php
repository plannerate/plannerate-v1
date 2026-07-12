<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Histórico de execuções da geração automática/template de planograma.
 *
 * Antes, o resultado de uma geração (capacity_report, validation_report, sugestões)
 * existia apenas como flash do Inertia — sumia no primeiro render e não era possível
 * auditar o que aconteceu numa geração passada. Esta tabela persiste cada execução,
 * viabilizando: geração assíncrona (em fila), consulta posterior do relatório e
 * comparação de ocupação entre execuções.
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::create('planogram_generation_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->nullable()->index();
            $table->foreignUlid('planogram_id')->comment('Planograma alvo da geração');
            $table->foreignUlid('gondola_id')->index()->comment('Gôndola alvo da geração');
            $table->foreignUlid('user_id')->nullable()->comment('Usuário que solicitou (será notificado)');

            $table->enum('status', ['queued', 'running', 'completed', 'failed'])
                ->default('queued')
                ->index();
            $table->enum('mode', ['automatic', 'template'])
                ->comment('Modo da geração: automático (template sintetizado) ou template existente');

            $table->json('config_snapshot')->comment('AutoGenerateConfigDTO->toArray() no momento do disparo');
            $table->foreignUlid('template_id')->nullable()->comment('Template escolhido (modo template)');
            $table->foreignUlid('synth_template_id')->nullable()->comment('Template sintetizado (modo automático)');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            // Métricas de precisão — a razão de existir deste histórico (ver Fase 2/3 do plano).
            $table->decimal('occupancy_avg', 5, 4)->nullable()->comment('Ocupação média das prateleiras (0-1)');
            $table->decimal('occupancy_min', 5, 4)->nullable();
            $table->decimal('occupancy_max', 5, 4)->nullable();
            $table->unsignedSmallInteger('iterations_run')->nullable()->comment('Iterações do loop de convergência (Fase 3)');
            $table->boolean('converged')->nullable()->comment('null = loop de convergência ainda não implementado');

            $table->json('capacity_report')->nullable();
            $table->json('validation_report')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['gondola_id', 'status']);
            $table->index(['gondola_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planogram_generation_runs');
    }
};
