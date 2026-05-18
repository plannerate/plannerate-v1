<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::create('planogram_template_slots', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('subtemplate_id', 26);
            $table->unsignedTinyInteger('module_number')->comment('Módulo — col E');
            $table->unsignedTinyInteger('shelf_order')->comment('Ordem lógica (1=chão, crescente para cima) — col F. Conversão: shelf_fisica = num_shelves - shelf_order');
            $table->string('category')->comment('Categoria — col G');
            $table->string('subcategory')->comment('Subcategoria — col H');
            $table->string('grouping')->comment('Agrupamento de exposição — col I');
            $table->string('grouping_normalized')->comment('grouping em lowercase+trim');
            $table->unsignedTinyInteger('min_facings')->default(1)->comment('Frentes mínimas por SKU — col J');
            $table->unsignedTinyInteger('priority')->default(1)->comment('Prioridade do slot (1=mais importante)');
            $table->enum('price_order', ['asc', 'desc', 'none'])->default('none')->comment('col K');
            $table->enum('size_order', ['asc', 'desc', 'none'])->default('none')->comment('col L');
            $table->enum('brand_exposure', ['vertical', 'horizontal', 'mixed'])->default('mixed')->comment('col M');
            $table->enum('flavor_exposure', ['vertical', 'horizontal', 'mixed'])->default('mixed')->comment('col N');
            $table->enum('space_fallback', ['reduce_c', 'reduce_facings', 'skip'])->default('reduce_c')->comment('col O');
            $table->boolean('use_target_stock')->default(false)->comment('col P');
            $table->unsignedTinyInteger('ordering')->default(1)->comment('Ordem de preenchimento quando múltiplos groupings competem pela mesma prateleira');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'subtemplate_id', 'module_number', 'shelf_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planogram_template_slots');
    }
};
