<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo global de tipos de espaço, copiado para `trade_space_types` de
     * cada tenant sob demanda. Não tem `tenant_id`: o escopo é a própria
     * conexão de tenant (multi-database).
     */
    public function up(): void
    {
        Schema::create('trade_space_type_library_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('prefix_code', 10)->nullable();
            $table->string('prefix_name')->nullable();
            $table->text('description')->nullable();
            $table->decimal('suggested_price', 10, 2)->nullable();
            $table->string('billing_mode', 20)->default('week');
            $table->string('image_path')->nullable();
            $table->decimal('suggested_width', 8, 2)->nullable();
            $table->decimal('suggested_height', 8, 2)->nullable();
            $table->decimal('suggested_depth', 8, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_space_type_library_items');
    }
};
