<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Divergências apontadas durante a Execução em Loja (ruptura, falta de espaço,
 * produto divergente, etc.). Divergências em estado pendente bloqueiam a
 * conclusão da execução.
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::create('workflow_execution_divergences', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id')->nullable();
            $table->ulid('tenant_id')->nullable();
            $table->foreignUlid('workflow_gondola_execution_id')
                ->constrained('workflow_gondola_executions')
                ->cascadeOnDelete();
            $table->string('type');
            $table->string('module_label')->nullable();
            $table->string('shelf_label')->nullable();
            $table->string('position_label')->nullable();
            $table->ulid('product_id')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('aberta');
            $table->text('resolution_notes')->nullable();
            $table->json('photos')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workflow_gondola_execution_id', 'status'], 'wf_exec_divergence_exec_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_execution_divergences');
    }
};
