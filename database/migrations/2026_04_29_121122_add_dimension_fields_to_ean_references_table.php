<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ean_references', function (Blueprint $table): void {
            // Colunas de dimensions
            $table->decimal('width', 10, 2)->nullable()->comment('Largura em cm (de dimensions)');
            $table->decimal('height', 10, 2)->nullable()->comment('Altura em cm (de dimensions)');
            $table->decimal('depth', 10, 2)->nullable()->comment('Profundidade em cm (de dimensions)');
            $table->decimal('weight', 10, 2)->nullable()->comment('Peso em gramas (de dimensions)');
            $table->string('unit')->default('cm')->comment('Unidade de medida (de dimensions)');
            $table->boolean('has_dimensions')->default(false)->comment('True = Com dimensão (width, height, depth > 0); False = Sem dimensão');
            $table->enum('dimension_status', ['draft', 'published'])->default('published')->comment('Status da dimensão (de dimensions)');
        });
    }

    public function down(): void
    {
        Schema::table('ean_references', function (Blueprint $table): void {
            $table->dropColumn([
                'width',
                'height',
                'depth',
                'weight',
                'unit',
                'has_dimensions',
                'dimension_status',
            ]);
        });
    }
};
