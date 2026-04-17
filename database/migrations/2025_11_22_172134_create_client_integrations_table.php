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
        Schema::create('client_integrations', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('client_id', 26);
            $table->string('integration_type')->index('client_integrations_integration_type_index');
            $table->string('identifier')->nullable()->comment("Identificador da loja/CNPJ/unidade (ex: loja_a, loja_b, cnpj_123)");
            $table->string('external_name')->nullable();
            $table->string('external_name_ean')->nullable();
            $table->string('external_name_status')->nullable();
            $table->string('external_name_sale_date')->nullable();
            $table->string('http_method')->default('POST')->nullable();
            $table->string('api_url')->nullable();
            $table->longText('authentication_headers')->nullable();
            $table->longText('authentication_body')->nullable();
            $table->longText('config')->nullable();
            $table->boolean('is_active')->default(1);
            $table->timestamp('last_sync')->nullable();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
            
            $table->unique(['client_id', 'integration_type', 'identifier'], 'client_integration_identifier_unique');
            $table->index(['client_id', 'is_active'], 'client_integrations_client_id_is_active_index');
        }); 
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_integrations');
    }
};
