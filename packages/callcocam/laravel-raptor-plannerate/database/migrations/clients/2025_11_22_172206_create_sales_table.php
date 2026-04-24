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
        Schema::create('sales', function (Blueprint $table) {
            $table->ulid('id')->primary()->comment('Identificador único da venda');
            $table->foreignUlid('tenant_id')->nullable()->index()->comment('Identificador do tenant (multi-tenant)');
            $table->foreignUlid('store_id')->nullable()->comment('Identificador da loja');
            $table->foreignUlid('product_id')->nullable()->comment('Identificador do produto');
            $table->string('ean', 13)->nullable();
            $table->string('codigo_erp')->nullable();
            $table->decimal('acquisition_cost', 12, 2)->nullable()->comment("Custo de aquisição do produto");
            $table->decimal('sale_price', 12, 2)->nullable()->comment("Preço de venda do produto");
            $table->decimal('total_profit_margin', 12, 2)->nullable()->comment("Margem de lucro unitária");
            $table->date('sale_date')->nullable()->comment("Data da venda");
            $table->string('promotion')->nullable();
            $table->decimal('total_sale_quantity', 10, 3)->nullable()->comment("Quantidade vendida (suporta vendas por peso)");
            $table->decimal('total_sale_value', 12, 2)->nullable()->comment("Valor total da venda");
            $table->decimal('margem_contribuicao', 15, 2)->nullable()->comment("Margem de Contribuição = total_sale_value - impostos - custo_medio");
            $table->longText('extra_data')->nullable()->comment("Dados extras da API (empresa_id, promoção, impostos, etc.)");
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['tenant_id', 'store_id', 'codigo_erp', 'sale_date', 'promotion']);
            $table->index(['product_id', 'margem_contribuicao']);
        }); 
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
