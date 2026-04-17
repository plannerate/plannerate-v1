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
        $metricsTable = $this->prefix.'metrics';

        if (Schema::connection($connection)->hasTable($metricsTable)) {
            return;
        }

        Schema::connection($connection)->create($metricsTable, function (Blueprint $table) use ($configStepsTable, $templatesTable) {
            $table->ulid('id')->primary();
            $table->string('workable_type');
            $table->ulid('workable_id');
            $table->foreignUlid('flow_config_step_id')->constrained($configStepsTable)->cascadeOnDelete();
            $table->foreignUlid('flow_step_template_id')->constrained($templatesTable)->cascadeOnDelete();
            $table->unsignedInteger('total_duration_minutes')->nullable();
            $table->unsignedInteger('effective_work_minutes')->nullable();
            $table->unsignedInteger('estimated_duration_minutes')->nullable();
            $table->integer('deviation_minutes')->nullable();
            $table->boolean('is_on_time')->default(false);
            $table->boolean('is_rework')->default(false);
            $table->unsignedInteger('rework_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['workable_type', 'workable_id']);
        });
    }

    public function down(): void
    {
        Schema::connection(config('flow.connection'))->dropIfExists($this->prefix.'metrics');
    }
};
