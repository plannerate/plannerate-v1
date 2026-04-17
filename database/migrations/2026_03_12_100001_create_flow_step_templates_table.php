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
        $templatesTable = $this->prefix.'step_templates';
        $connection = config('flow.connection');

        if (Schema::connection($connection)->hasTable($templatesTable)) {
            return;
        }

        Schema::connection($connection)->create($templatesTable, function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id')->nullable();
            $table->ulid('tenant_id')->nullable();
            $table->foreignUlid('flow_id')->nullable()->constrained($this->prefix.'flows')->nullOnDelete();
            $table->ulid('template_next_step_id')->nullable();
            $table->ulid('template_previous_step_id')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->string('category')->nullable();
            $table->json('tags')->nullable();
            $table->unsignedInteger('suggested_order')->default(0);
            $table->unsignedInteger('estimated_duration_days')->nullable();
            $table->ulid('default_role_id')->nullable();
            $table->string('color')->nullable();
            $table->string('icon')->nullable();
            $table->boolean('is_required_by_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($connection)->table($templatesTable, function (Blueprint $table) use ($templatesTable) {
            $table->foreign('template_next_step_id')
                ->references('id')
                ->on($templatesTable)
                ->nullOnDelete();

            $table->foreign('template_previous_step_id')
                ->references('id')
                ->on($templatesTable)
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection(config('flow.connection'))->dropIfExists($this->prefix.'step_templates');
    }
};
