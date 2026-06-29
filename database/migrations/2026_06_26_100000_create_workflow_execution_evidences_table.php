<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evidências (fotos/arquivos) registradas durante a Execução em Loja.
 * Cada linha representa um arquivo anexado a uma execução de gôndola.
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::create('workflow_execution_evidences', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id')->nullable();
            $table->ulid('tenant_id')->nullable();
            $table->foreignUlid('workflow_gondola_execution_id')
                ->constrained('workflow_gondola_executions')
                ->cascadeOnDelete();
            $table->string('type')->default('general_photo');
            $table->string('module_label')->nullable();
            $table->ulid('product_id')->nullable();
            $table->string('file_path');
            $table->string('file_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workflow_gondola_execution_id', 'type'], 'wf_exec_evidence_exec_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_execution_evidences');
    }
};
