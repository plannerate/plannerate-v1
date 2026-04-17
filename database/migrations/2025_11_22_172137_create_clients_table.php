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
        Schema::create('clients', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26)->nullable();
            $table->string('database')->nullable()->after('tenant_id')->comment('Nome do banco de dados dedicado (se multi-database)');
            $table->char('user_id', 26)->nullable()->index('clients_user_id_index');
            $table->string('name');
            $table->string('slug')->nullable()->unique('clients_slug_unique');
            $table->string('cnpj')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->longText('bcg_calculos')->nullable();
            $table->longText('abc_calculos')->nullable();
            $table->longText('stock_calculos')->nullable();
            $table->string('description')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->string('client_api_type')->nullable()->comment('Tipo de API do Cliente');
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
        }); 
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
