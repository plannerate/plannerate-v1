<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        Schema::create('global_planogram_template_products', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('template_id')->comment('FK → global_planogram_templates');
            $table->string('ean')->comment('EAN do produto — col A');
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
            $table->index(['template_id', 'grouping_normalized'], 'global_tmp_products_idx');
            $table->index(['template_id', 'ean']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_planogram_template_products');
    }
};
