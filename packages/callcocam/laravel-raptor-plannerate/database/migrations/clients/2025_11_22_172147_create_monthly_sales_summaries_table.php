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
        Schema::create('monthly_sales_summaries', function (Blueprint $table) {
            $table->ulid('id')->primary()->comment('Identificador único da sumarização mensal');
            $table->foreignUlid('tenant_id')->nullable()->index()->comment('Identificador do tenant (multi-tenant)');
            $table->foreignUlid('store_id')->nullable()->index()->comment('Identificador da loja');
            $table->foreignUlid('product_id')->nullable()->index()->comment('Identificador do produto');
            $table->string('ean', 13)->nullable()->comment("Código EAN do produto");
            $table->string('codigo_erp')->nullable()->comment("Código ERP do produto");
            $table->decimal('acquisition_cost', 12, 2)->nullable()->comment("Soma do custo de aquisição do mês");
            $table->decimal('sale_price', 12, 2)->nullable()->comment("Soma do preço de venda do mês");
            $table->decimal('total_profit_margin', 12, 2)->nullable()->comment("Soma da margem de lucro do mês");
            $table->date('sale_month')->nullable()->index('idx_monthly_month')->comment("Mês de referência (primeiro dia do mês: YYYY-MM-01)");
            $table->string('promotion')->nullable()->index('idx_monthly_promotion')->comment("Promoção (S=Sim, N=Não) - vendas promocionais e normais são agregadas separadamente");
            $table->integer('total_sale_quantity')->nullable()->comment("Soma da quantidade vendida no mês");
            $table->decimal('total_sale_value', 12, 2)->nullable()->comment("Soma do valor total de vendas no mês");
            $table->decimal('margem_contribuicao', 15, 2)->nullable()->comment("Soma das margens de contribuição do período (agregado das vendas)");
            $table->longText('extra_data')->nullable()->comment("Agregação dos dados extras do mês (empresa_id, promoção, impostos, etc.)");
            $table->timestamps();
            
            $table->unique(['tenant_id', 'store_id', 'codigo_erp', 'sale_month', 'promotion']);
            $table->index(['ean', 'sale_month']);
            $table->index(['ean', 'store_id', 'sale_month']);
            $table->index(['store_id', 'sale_month']);
            $table->index(['product_id', 'margem_contribuicao']);
        }); 
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_sales_summaries');
    }
};
