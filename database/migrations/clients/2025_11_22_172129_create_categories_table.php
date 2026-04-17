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
        Schema::create('categories', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26)->nullable();
            $table->char('user_id', 26)->nullable();
            $table->char('category_id', 26)->nullable()->comment("Parent category ID");
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('level_name')->nullable()->comment("Nome do nível da hierarquia (ex: Categoria, Subcategoria)");
            $table->integer('codigo')->nullable()->comment("Código único da categoria");
            $table->enum('status', ['draft', 'published', 'importer'])->default('draft');
            $table->string('description')->nullable();
            $table->string('nivel')->nullable();
            $table->integer('hierarchy_position')->nullable()->comment("Posição na hierarquia (1-7)");
            $table->text('full_path')->nullable()->comment("Caminho completo da hierarquia separado por >");
            $table->longText('hierarchy_path')->nullable()->comment("Array JSON com o caminho da hierarquia");
            $table->boolean('is_placeholder')->default(0)->comment("Indica se é uma categoria placeholder (SEM X)");
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
            
            $table->unique(['tenant_id', 'slug'], 'categories_tenant_id_slug_unique');
            $table->index(['tenant_id', 'nivel'], 'categories_tenant_id_nivel_index');
            $table->index(['tenant_id', 'hierarchy_position'], 'categories_tenant_id_hierarchy_position_index');
            $table->index(['tenant_id', 'is_placeholder'], 'categories_tenant_id_is_placeholder_index');
        }); 
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
