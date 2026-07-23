<?php

use Callcocam\LaravelRaptorTrade\Support\Database\TradeSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modelo reutilizável de etapa de workflow. Uma categoria de templates
     * (ex.: "Montagem de ponta") vira uma sequência de etapas aplicada a uma
     * atividade. `allowed_users` restringe quem pode ser responsável pela etapa.
     */
    public function up(): void
    {
        Schema::create('trade_workflow_step_templates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            TradeSchema::owner($table)->nullable()->index();
            TradeSchema::reference($table, 'user_id', 'user')->nullable()->index();

            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->string('category')->nullable();
            $table->json('tags')->nullable();

            $table->integer('suggested_order')->default(0);
            $table->integer('estimated_duration_days')->nullable();
            $table->boolean('is_required_by_default')->default(false);
            $table->boolean('is_active')->default(true);

            $table->json('metadata')->nullable();
            $table->string('color')->default('blue');
            $table->string('icon')->nullable();
            $table->json('allowed_users')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique([TradeSchema::ownerColumn(), 'slug']);
            $table->index([TradeSchema::ownerColumn(), 'is_active']);
            $table->index([TradeSchema::ownerColumn(), 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_workflow_step_templates');
    }
};
