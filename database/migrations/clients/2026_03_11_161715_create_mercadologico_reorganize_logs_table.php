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
        Schema::create('mercadologico_reorganize_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id')->index()->nullable();
            $table->ulid('tenant_id')->index()->nullable();
            $table->json('snapshot_backup')->nullable()->comment('Backup: categories, products category_id, planograms category_id');
            $table->json('agent_response')->nullable()->comment('Resposta do agente: renames, merges, reasoning');
            $table->string('status', 20)->default('suggestion')->comment('suggestion|applied|discarded');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mercadologico_reorganize_logs');
    }
};
