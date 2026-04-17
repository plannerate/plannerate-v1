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
        Schema::create('shelves', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26)->nullable();
            $table->char('user_id', 26)->nullable()->index('shelves_user_id_index');
            $table->char('section_id', 26)->nullable();
            $table->string('code', 50)->nullable()->unique('shelves_code_unique');
            $table->string('product_type')->default('normal');
            $table->decimal('shelf_width', 10, 2)->default(4.00)->comment('Largura da prateleira em cm');
            $table->decimal('shelf_height', 10, 2)->default(4.00)->comment('Altura da prateleira em cm');
            $table->decimal('shelf_depth', 10, 2)->default(40.00)->comment('Profundidade da prateleira em cm');
            $table->decimal('shelf_position', 10, 2)->default(0.00)->comment('Posição vertical em cm');
            $table->integer('ordering')->default(0);
            $table->enum('alignment', ['left', 'right', 'center', 'justify'])->nullable();
            $table->integer('spacing')->default(0);
            $table->longText('settings')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shelves');
    }
};
