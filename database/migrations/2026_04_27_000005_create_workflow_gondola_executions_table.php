<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected string $connection = 'tenant';

    public function up(): void
    {
        Schema::create('workflow_gondola_executions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id')->nullable();
            $table->ulid('tenant_id')->nullable();
            $table->ulid('gondola_id')->nullable()->index();
            $table->foreignUlid('workflow_planogram_step_id')->constrained('workflow_planogram_steps')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->ulid('current_responsible_id')->nullable();
            $table->ulid('execution_started_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('sla_date')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['gondola_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_gondola_executions');
    }
};
