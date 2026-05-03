<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection($this->connection)->create('integration_sync_days', function (Blueprint $table): void {
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

            $table->unique(
                ['tenant_integration_id', 'resource', 'reference_date'],
                'int_sync_days_tenant_resource_date_uq'
            );
            $table->index(
                ['resource', 'reference_date', 'status'],
                'int_sync_days_resource_date_status_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('integration_sync_days');
    }
};
