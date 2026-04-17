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
        Schema::create('product_provider', function (Blueprint $table) {
            $table->char('product_id', 26);
            $table->string('codigo_erp')->nullable();
            $table->char('provider_id', 26);
            $table->char('principal', 1)->default('N')->comment("Indica se é o fornecedor principal (S/N)");
            $table->timestamps();
            
            // Constraint unique composta para permitir upsert
            $table->unique(['product_id', 'provider_id'], 'product_provider_unique');
        }); 
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_provider');
    }
};
