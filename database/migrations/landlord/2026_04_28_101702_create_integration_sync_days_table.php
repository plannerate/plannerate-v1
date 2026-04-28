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
        Schema::connection('landlord')->create('integration_sync_days', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_integration_id')->constrained('tenant_integrations')->cascadeOnDelete();
            $table->string('resource', 32);
            $table->date('reference_date');
            $table->string('status', 24)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_integration_id', 'resource', 'reference_date']);
            $table->index(['resource', 'reference_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('integration_sync_days');
    }
};
