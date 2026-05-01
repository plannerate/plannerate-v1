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
        Schema::create('segments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->nullable()->index();
            $table->foreignUlid('user_id')->nullable()->index();
            $table->foreignUlid('shelf_id')->nullable()->index();
            $table->integer('width')->nullable();
            $table->decimal('distributed_width', 8, 2)->nullable()->comment('Largura calculada para distribuição em justify (em cm)');
            $table->integer('height')->nullable();
            $table->integer('ordering')->default(0);
            $table->enum('alignment', ['left', 'right', 'center', 'justify'])->nullable();
            $table->integer('position')->nullable();
            $table->integer('quantity')->default(1);
            $table->integer('spacing')->nullable();
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
        Schema::dropIfExists('segments');
    }
};
