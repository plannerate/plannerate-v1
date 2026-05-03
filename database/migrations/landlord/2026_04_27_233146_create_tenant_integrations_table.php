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
        Schema::connection($this->connection)->create('tenant_integrations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('integration_type');
            $table->string('identifier')->nullable()->comment('Identificador da loja/CNPJ/unidade');
            $table->string('external_name')->nullable();
            $table->string('external_name_ean')->nullable();
            $table->string('external_name_status')->nullable();
            $table->string('external_name_sale_date')->nullable();
            $table->string('http_method')->default('POST');
            $table->string('api_url')->nullable();
            $table->json('authentication_headers')->nullable();
            $table->json('authentication_body')->nullable();
            $table->json('config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sync')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique('tenant_id');
            $table->index('integration_type');
            $table->index(['tenant_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('tenant_integrations');
    }
};
