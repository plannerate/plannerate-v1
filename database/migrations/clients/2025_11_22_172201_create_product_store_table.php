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
        Schema::create('product_store', function (Blueprint $table) {
            $table->char('product_id', 26)->index('idx_product_id')->comment("Identificador único do produto");
            $table->char('store_id', 26)->index()->comment("Codificador único da loja");
            $table->timestamp('sync_date')->nullable()->index('idx_sync_date')->comment("Data da última sincronização");
            $table->integer('page_number')->nullable()->comment("Página onde foi sincronizado");
            $table->timestamps();
            
            $table->unique(['store_id', 'product_id'], 'unique_store_product');
            $table->index(['store_id', 'sync_date'], 'idx_store_sync');
        }); 
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_store');
    }
};
