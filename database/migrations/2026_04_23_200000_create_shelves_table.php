<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shelves', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->nullable()->index();
            $table->foreignUlid('user_id')->nullable()->index();
            $table->foreignUlid('section_id')->nullable()->index();
            $table->string('code', 50)->nullable()->unique();
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
            $table->softDeletes();
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
