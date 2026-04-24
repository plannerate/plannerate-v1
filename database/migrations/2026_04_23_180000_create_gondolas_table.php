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
        Schema::create('gondolas', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->nullable()->index();
            $table->foreignUlid('user_id')->nullable()->index();
            $table->foreignUlid('planogram_id')->nullable()->index();
            $table->foreignUlid('linked_map_gondola_id')->nullable();
            $table->string('linked_map_gondola_category')->nullable();
            $table->string('name');
            $table->string('slug')->nullable()->unique();
            $table->integer('num_modulos')->default(1);
            $table->string('location')->nullable();
            $table->string('side')->nullable();
            $table->enum('flow', ['left_to_right', 'right_to_left'])->default('left_to_right');
            $table->enum('alignment', ['left', 'right', 'center', 'justify'])->default('justify');
            $table->float('scale_factor')->default(1)->comment('Fator de escala para visualização');
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
        Schema::dropIfExists('gondolas');
    }
};
