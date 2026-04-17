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
        Schema::create('integration_sync_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('client_id', 26);
            $table->char('store_id', 26);
            $table->string('integration_type'); // visao, sysmo
            $table->enum('sync_type', ['sales', 'products', 'purchases']); // GENÉRICO
            $table->date('sync_date'); // Dia que está tentando sincronizar
            $table->enum('status', ['pending', 'processing', 'success', 'failed', 'skipped'])->default('pending');
            $table->integer('retry_count')->default(0);
            $table->integer('consecutive_failures')->default(0); // Falhas consecutivas globais
            $table->integer('total_items')->nullable();
            $table->text('error_message')->nullable();
            $table->json('error_details')->nullable(); // Stack trace, response
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamps();

            // Foreign keys removidas para suportar multi-database
            // client_id e store_id são mantidos como ULID simples sem constraints

            $table->unique(['client_id', 'store_id', 'sync_type', 'sync_date'], 'unique_client_store_type_date');
            $table->index(['status', 'retry_count']);
            $table->index(['sync_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_sync_logs');
    }
};
