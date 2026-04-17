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
        $presetsTable = $this->prefix.'presets';
        $templatesTable = $this->prefix.'step_templates';
        $connection = config('flow.connection');
        $presetStepsTable = $this->prefix.'preset_steps';

        if (Schema::connection($connection)->hasTable($presetStepsTable)) {
            return;
        }

        Schema::connection($connection)->create($presetStepsTable, function (Blueprint $table) use ($presetsTable, $templatesTable) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workflow_preset_id')->constrained($presetsTable)->cascadeOnDelete();
            $table->foreignUlid('workflow_step_template_id')->constrained($templatesTable)->cascadeOnDelete();
            $table->unsignedInteger('order')->default(0);
            $table->string('name')->nullable();
            $table->ulid('default_role_id')->nullable();
            $table->ulid('suggested_responsible_id')->nullable();
            $table->unsignedInteger('estimated_duration_days')->nullable();
            $table->boolean('is_required')->default(true);
            $table->boolean('auto_assign_role')->default(false);
            $table->boolean('auto_assign_user')->default(false);
            $table->boolean('allow_skip')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection(config('flow.connection'))->dropIfExists($this->prefix.'preset_steps');
    }
};
