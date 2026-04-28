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
        Schema::create('products', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id')->nullable()->index();
            $table->ulid('user_id')->nullable();
            $table->ulid('image_id')->nullable();
            $table->ulid('category_id')->nullable()->index();
            $table->ulid('client_id')->nullable();

            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->string('ean', 255)->nullable();
            $table->string('codigo_erp')->nullable();

            $table->boolean('stackable')->default(false);
            $table->boolean('perishable')->default(false);
            $table->boolean('flammable')->default(false);
            $table->boolean('hangable')->default(false);
            $table->boolean('no_sales')->default(false);
            $table->boolean('no_purchases')->default(false);

            $table->text('description')->nullable();
            $table->string('sales_status')->nullable();
            $table->string('sales_purchases')->nullable();
            $table->enum('status', ['draft', 'published', 'synced', 'error'])->default('draft');
            $table->string('sync_source')->nullable();
            $table->timestamp('sync_at')->nullable();
            $table->string('url')->nullable();

            $table->string('type')->nullable();
            $table->string('reference')->nullable();
            $table->string('fragrance')->nullable();
            $table->string('flavor')->nullable();
            $table->string('color')->nullable();
            $table->string('brand')->nullable();
            $table->string('subbrand')->nullable();
            $table->string('packaging_type')->nullable();
            $table->string('packaging_size')->nullable();
            $table->string('measurement_unit')->nullable();
            $table->string('packaging_content')->nullable();
            $table->string('unit_measure')->nullable();
            $table->string('auxiliary_description')->nullable();
            $table->string('additional_information')->nullable();
            $table->string('sortiment_attribute')->nullable();

            // Colunas de dimensions
            $table->decimal('width', 10, 2)->nullable()->comment('Largura em cm (de dimensions)');
            $table->decimal('height', 10, 2)->nullable()->comment('Altura em cm (de dimensions)');
            $table->decimal('depth', 10, 2)->nullable()->comment('Profundidade em cm (de dimensions)');
            $table->decimal('weight', 10, 2)->nullable()->comment('Peso em gramas (de dimensions)');
            $table->string('unit')->default('cm')->comment('Unidade de medida (de dimensions)');
            $table->boolean('has_dimensions')->default(false)->comment('True = Com dimensão (width, height, depth > 0); False = Sem dimensão');
            $table->enum('dimension_status', ['draft', 'published'])->default('published')->comment('Status da dimensão (de dimensions)');
            $table->float('current_stock')->nullable();
            $table->date('last_purchase_date')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'ean']);
            $table->unique(['tenant_id', 'slug']);
            $table->index(['sync_source', 'sync_at']);
            $table->index(['tenant_id', 'dimensions_ean']);
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
