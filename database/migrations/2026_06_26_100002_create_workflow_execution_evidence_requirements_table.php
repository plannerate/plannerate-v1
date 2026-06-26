<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuração de evidências obrigatórias por categoria/tipo. Quando não há
 * configuração, o serviço aplica o padrão: 1 foto geral + 1 por módulo.
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::create('workflow_execution_evidence_requirements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id')->nullable();
            $table->ulid('tenant_id')->nullable();
            $table->ulid('category_id')->nullable()->index();
            $table->string('type');
            $table->unsignedInteger('min_count')->default(1);
            $table->boolean('per_module')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_execution_evidence_requirements');
    }
};
