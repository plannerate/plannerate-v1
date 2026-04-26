<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_planogram_steps', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id')->nullable();
            $table->ulid('tenant_id')->nullable();
            $table->ulid('planogram_id')->nullable()->index();
            $table->foreignUlid('workflow_template_id')->constrained('workflow_templates')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('estimated_duration_days')->nullable();
            $table->ulid('role_id')->nullable();
            $table->boolean('is_required')->default(true);
            $table->string('status')->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['planogram_id', 'workflow_template_id'], 'wf_planogram_step_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_planogram_steps');
    }
};
