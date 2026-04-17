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
        $connection = config('flow.connection');
        $historiesTable = $this->prefix.'histories';

        if (Schema::connection($connection)->hasTable($historiesTable)) {
            return;
        }

        Schema::connection($connection)->create($historiesTable, function (Blueprint $table) use ($configStepsTable) {
            $table->ulid('id')->primary();
            $table->string('workable_type');
            $table->ulid('workable_id');
            $table->ulid('flow_config_step_id')->nullable();
            $table->string('action');
            $table->ulid('from_step_id')->nullable();
            $table->ulid('to_step_id')->nullable();
            $table->ulid('user_id')->nullable();
            $table->ulid('previous_responsible_id')->nullable();
            $table->ulid('new_responsible_id')->nullable();
            $table->timestamp('performed_at');
            $table->unsignedInteger('duration_in_step_minutes')->nullable();
            $table->timestamp('sla_at_transition')->nullable();
            $table->boolean('was_overdue')->default(false);
            $table->text('notes')->nullable();
            $table->json('snapshot')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['workable_type', 'workable_id']);
        });
    }

    public function down(): void
    {
        Schema::connection(config('flow.connection'))->dropIfExists($this->prefix.'histories');
    }
};
