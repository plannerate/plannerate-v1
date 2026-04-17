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
        Schema::create('purchases', function (Blueprint $table) {
            $table->char('id', 26)->primary()->comment("Identificador único da compra");
            $table->char('tenant_id', 26)->nullable()->index('purchases_tenant_id_index')->comment("Identificador do tenant (multi-tenant)");
            $table->char('client_id', 26)->nullable()->index('purchases_client_id_index')->comment("Identificador do cliente");
            $table->char('user_id', 26)->nullable();
            $table->char('provider_id', 26)->nullable();
            $table->char('store_id', 26)->nullable()->index('purchases_store_id_index')->comment("Identificador da loja");
            $table->char('product_id', 26)->nullable()->index('purchases_product_id_index')->comment("Identificador do produto");
            $table->string('ean', 13)->nullable()->comment("Código EAN do produto");
            $table->string('codigo_erp')->nullable()->comment("Código ERP do produto");
            $table->date('entry_date')->nullable()->comment("Data de entrada");
            $table->integer('entry_quantity')->nullable()->comment("Quantidade de entrada");
            $table->date('last_purchase_date')->nullable()->comment("Data da última compra");
            $table->integer('current_stock')->nullable()->comment("Estoque atual");
            $table->timestamps();
            
            $table->index(['ean', 'entry_date'], 'idx_purchases_ean_date');
            $table->index(['ean', 'store_id', 'entry_date'], 'idx_purchases_ean_store_date');
        }); 
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
