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
        Schema::create('products', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->nullable()->index();
            $table->foreignUlid('user_id')->nullable();
            $table->foreignUlid('image_id')->nullable();
            $table->foreignUlid('category_id')->nullable();
            $table->foreignUlid('store_id')->nullable()->comment('Identificador da loja');
            $table->string('name')->nullable()->comment("Nome do produto");
            $table->string('slug')->nullable();
            $table->string('ean', 50)->nullable();
            $table->string('codigo_erp')->nullable();
            $table->boolean('stackable')->default(0);
            $table->boolean('perishable')->default(0);
            $table->boolean('flammable')->default(0);
            $table->boolean('hangable')->default(0);
            $table->text('description')->nullable();
            $table->string('sales_status')->nullable()->comment("Status de vendas do produto: active, inactive, discontinued");
            $table->string('sales_purchases')->nullable()->comment("Disponibilidade de vendas e compras: available, unavailable, limited");
            $table->enum('status', ['draft', 'published', 'synced', 'error'])->default('draft');
            $table->string('sync_source')->nullable()->comment("Fonte da sincronização (api, manual, import)");
            $table->timestamp('sync_at')->nullable()->comment("Data/hora da última sincronização");
            $table->boolean('no_sales')->default(0);
            $table->boolean('no_purchases')->default(0);
            $table->string('url')->nullable();
            
            // Colunas de product_additional_data
            $table->string('type')->nullable()->comment("Tipo do produto (de product_additional_data)");
            $table->string('reference')->nullable()->comment("Referência do produto (de product_additional_data)");
            $table->string('fragrance')->nullable()->comment("Fragrância (de product_additional_data)");
            $table->string('flavor')->nullable()->comment("Sabor (de product_additional_data)");
            $table->string('color')->nullable()->comment("Cor (de product_additional_data)");
            $table->string('brand')->nullable()->comment("Marca (de product_additional_data)");
            $table->string('subbrand')->nullable()->comment("Submarca (de product_additional_data)");
            $table->string('packaging_type')->nullable()->comment("Tipo de embalagem (de product_additional_data)");
            $table->string('packaging_size')->nullable()->comment("Tamanho da embalagem (de product_additional_data)");
            $table->string('measurement_unit')->nullable()->comment("Unidade de medida (de product_additional_data)");
            $table->string('packaging_content')->nullable()->comment("Conteúdo da embalagem (de product_additional_data)");
            $table->string('unit_measure')->nullable()->comment("Unidade de medida alternativa (de product_additional_data)");
            $table->string('auxiliary_description')->nullable()->comment("Descrição auxiliar (de product_additional_data)");
            $table->string('additional_information')->nullable()->comment("Informações adicionais (de product_additional_data)");
            $table->string('sortiment_attribute')->nullable()->comment("Atributo de sortimento (de product_additional_data)");
            
            // Colunas de dimensions
            $table->decimal('width', 10, 2)->nullable()->comment("Largura em cm (de dimensions)");
            $table->decimal('height', 10, 2)->nullable()->comment("Altura em cm (de dimensions)");
            $table->decimal('depth', 10, 2)->nullable()->comment("Profundidade em cm (de dimensions)");
            $table->decimal('weight', 10, 2)->nullable()->comment("Peso em gramas (de dimensions)");
            $table->string('unit')->default('cm')->comment("Unidade de medida (de dimensions)");
            $table->boolean('has_dimensions')->default(false)->comment('True = Com dimensão (width, height, depth > 0); False = Sem dimensão');
            $table->enum('dimension_status', ['draft', 'published'])->default('published')->comment("Status da dimensão (de dimensions)");
            $table->float('current_stock')->nullable();
            
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
            
            $table->unique(['tenant_id', 'ean'], 'products_tenant_id_ean_unique');
            $table->unique(['tenant_id', 'slug'], 'products_tenant_id_slug_unique');
            $table->index(['sync_source', 'sync_at'], 'products_sync_source_sync_at_index');
            $table->index(['tenant_id', 'client_id'], 'products_tenant_id_client_id_index');
        }); 
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
