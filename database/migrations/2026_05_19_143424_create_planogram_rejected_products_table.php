<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::create('planogram_rejected_products', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->nullable()->index();
            $table->foreignUlid('planogram_id')->comment('Planograma que gerou a rejeição');
            $table->foreignUlid('gondola_id')->index()->comment('Gôndola que gerou a rejeição');
            $table->string('product_id')->comment('ID do produto rejeitado');
            $table->string('product_name')->nullable();
            $table->string('ean')->nullable()->index();
            $table->string('image_url')->nullable();
            $table->decimal('product_width', 8, 2)->nullable()->comment('Largura em cm');
            $table->decimal('product_height', 8, 2)->nullable()->comment('Altura em cm');
            $table->enum('rejection_reason', ['no_horizontal_space', 'height_exceeds_shelf', 'no_shelf_at_level']);
            $table->foreignUlid('slot_id')->nullable()->comment('Slot do template onde tentou posicionar');
            $table->string('grouping')->nullable()->comment('Agrupamento/categoria do slot');
            $table->unsignedSmallInteger('module_number')->nullable();
            $table->unsignedSmallInteger('shelf_order')->nullable();
            $table->timestamps();

            $table->index(['gondola_id', 'planogram_id']);
            $table->index(['product_id', 'gondola_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planogram_rejected_products');
    }
};
