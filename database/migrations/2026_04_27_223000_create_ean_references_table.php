<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected string $connection = 'tenant';

    public function up(): void
    {
        Schema::create('ean_references', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id')->index();
            $table->string('ean', 32);
            $table->foreignUlid('category_id')->nullable()->constrained('categories')->nullOnDelete();

            $table->text('reference_description')->nullable();
            $table->string('brand')->nullable();
            $table->string('subbrand')->nullable();
            $table->string('packaging_type')->nullable();
            $table->string('packaging_size')->nullable();
            $table->string('measurement_unit')->nullable();

            // Colunas de dimensions
            $table->decimal('width', 10, 2)->nullable()->comment('Largura em cm (de dimensions)');
            $table->decimal('height', 10, 2)->nullable()->comment('Altura em cm (de dimensions)');
            $table->decimal('depth', 10, 2)->nullable()->comment('Profundidade em cm (de dimensions)');
            $table->decimal('weight', 10, 2)->nullable()->comment('Peso em gramas (de dimensions)');
            $table->string('unit')->default('cm')->comment('Unidade de medida (de dimensions)');
            $table->boolean('has_dimensions')->default(false)->comment('True = Com dimensão (width, height, depth > 0); False = Sem dimensão');
            $table->enum('dimension_status', ['draft', 'published'])->default('published')->comment('Status da dimensão (de dimensions)');

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'ean']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ean_references');
    }
};
