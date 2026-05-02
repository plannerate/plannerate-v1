<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        $templatesTable = 'workflow_templates';

        Schema::create($templatesTable, function (Blueprint $table) use ($templatesTable) {
            $table->ulid('id')->primary();
            $table->ulid('user_id')->nullable();
            $table->ulid('tenant_id')->nullable();
            $table->foreignUlid('template_next_step_id')->nullable()->constrained($templatesTable)->nullOnDelete();
            $table->foreignUlid('template_previous_step_id')->nullable()->constrained($templatesTable)->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('suggested_order')->default(0);
            $table->unsignedInteger('estimated_duration_days')->nullable();
            $table->ulid('default_role_id')->nullable();
            $table->string('color')->nullable();
            $table->string('icon')->nullable();
            $table->boolean('is_required_by_default')->default(false);
            $table->string('status')->default('draft');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_templates');
    }
};
