<?php

use Callcocam\LaravelRaptorTrade\Support\Database\TradeSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Instância de execução de uma etapa de workflow numa atividade. Cada linha
     * é um template aplicado a uma atividade, com responsável, datas e status
     * próprios (pending → in_progress → completed/skipped/failed).
     */
    public function up(): void
    {
        Schema::create('trade_workflow_steps', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            TradeSchema::owner($table)->nullable()->index();

            $table->foreignUlid('activity_id')->index();
            $table->foreignUlid('workflow_step_template_id')->index();

            $table->string('status', 20)->default('pending');

            $table->date('scheduled_date')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('due_date')->nullable();

            TradeSchema::reference($table, 'assignee_id', 'user')->nullable()->index();
            TradeSchema::reference($table, 'completed_by', 'user')->nullable();

            $table->json('step_data')->nullable();
            $table->text('notes')->nullable();
            $table->text('completion_notes')->nullable();

            $table->integer('step_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->boolean('can_skip')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['activity_id', 'status']);
            $table->index(['assignee_id', 'status']);
            $table->index('step_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_workflow_steps');
    }
};
