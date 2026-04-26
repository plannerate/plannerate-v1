<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_histories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id')->nullable();
            $table->foreignUlid('workflow_gondola_execution_id')
                ->constrained('workflow_gondola_executions')
                ->cascadeOnDelete();
            $table->string('action');
            $table->ulid('from_step_id')->nullable();
            $table->ulid('to_step_id')->nullable();
            $table->ulid('previous_responsible_id')->nullable();
            $table->ulid('new_responsible_id')->nullable();
            $table->text('description')->nullable();
            $table->json('snapshot')->nullable();
            $table->boolean('can_restore')->default(true);
            $table->timestamp('performed_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workflow_gondola_execution_id', 'performed_at'], 'wf_histories_execution_performed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_histories');
    }
};
