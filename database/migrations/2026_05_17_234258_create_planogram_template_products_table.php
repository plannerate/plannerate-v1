<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::create('planogram_template_products', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('template_id', 26);
            $table->string('ean')->comment('EAN do produto — col A');
            $table->char('product_id', 26)->nullable()->comment('FK → products (resolvido no import)');
            $table->string('description')->comment('Descrição — col B');
            $table->string('department')->comment('Departamento — col C');
            $table->string('category')->comment('Categoria — col D');
            $table->string('subcategory')->comment('Subcategoria — col E');
            $table->string('grouping')->comment('Agrupamento — col F (chave de vínculo com slot)');
            $table->string('grouping_normalized')->comment('grouping em lowercase+trim');
            $table->string('brand')->comment('Marca — col G');
            $table->string('package_type')->nullable()->comment('Tipo embalagem — col H');
            $table->string('package_content')->nullable()->comment('Conteúdo embalagem — col I');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['template_id', 'grouping_normalized', 'product_id'], 'tmp_products_generation_idx');
            $table->index(['tenant_id', 'ean']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planogram_template_products');
    }
};
