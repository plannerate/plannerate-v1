<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('gondola_analyses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->nullable()->index();
            $table->foreignUlid('gondola_id')->comment('Identificador da gondola');
            // Foreign key removida para suportar multi-database
            // gondola_id é mantido como ULID simples sem constraint
            $table->enum('type', ['abc', 'stock', 'bcg'])->comment('Tipo de análise');
            $table->json('data')->comment('Dados da análise (classificações, métricas, etc.)');
            $table->json('summary')->nullable()->comment('Resumo executivo da análise');
            $table->timestamp('analyzed_at')->comment('Data/hora da última análise');
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index(['gondola_id', 'type']);
            $table->unique(['gondola_id', 'type', 'deleted_at'], 'unique_gondola_analysis');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gondola_analyses');
    }
};
