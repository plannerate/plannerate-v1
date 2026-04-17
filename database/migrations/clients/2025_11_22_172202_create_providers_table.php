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
        Schema::create('providers', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26)->nullable()->index('providers_tenant_id_index')->comment("Tenant ID of the provider");
            $table->char('user_id', 26)->nullable()->index('providers_user_id_index')->comment("User ID of the provider");
            $table->string('code')->nullable()->comment("Código do fornecedor (não único, pode haver duplicatas)");
            $table->string('name')->nullable()->comment("Name of the provider");
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('street')->nullable();
            $table->string('number')->nullable();
            $table->string('complement')->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip')->nullable();
            $table->string('cnpj')->nullable()->comment("CNPJ of the provider");
            $table->string('status')->default('published')->after('cnpj')->comment("Status do fornecedor (published, draft, archived)");
            $table->string('is_default')->default('S')->comment("S - Sim, N - Não");
            $table->text('description')->nullable();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
        }); 
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
