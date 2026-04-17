<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected string $prefix;

    public function __construct()
    {
        $this->prefix = config('flow.table_prefix', 'flow_');
    }

    public function up(): void
    {
        $configStepsTable = $this->prefix.'config_steps';
        $templatesTable = $this->prefix.'step_templates';
        $connection = config('flow.connection');
        $executionsTable = $this->prefix.'executions';

        if (Schema::connection($connection)->hasTable($executionsTable)) {
            return;
        }

        Schema::connection($connection)->create($executionsTable, function (Blueprint $table) use ($configStepsTable, $templatesTable) {
            $table->ulid('id')->primary();
            $table->string('workable_type');
            $table->ulid('workable_id');
            $table->foreignUlid('flow_config_step_id')->constrained($configStepsTable)->cascadeOnDelete();
            $table->foreignUlid('flow_step_template_id')->constrained($templatesTable)->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->ulid('current_responsible_id')->nullable();
            $table->ulid('execution_started_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('sla_date')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->unsignedInteger('paused_duration_minutes')->default(0);
            $table->unsignedInteger('actual_duration_minutes')->nullable();
            $table->unsignedInteger('estimated_duration_days')->nullable();
            $table->text('notes')->nullable();
            $table->json('context')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workable_type', 'workable_id']);
        });
    }

    public function down(): void
    {
        Schema::connection(config('flow.connection'))->dropIfExists($this->prefix.'executions');
    }
};
