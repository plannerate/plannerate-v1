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
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26)->nullable();
            $table->char('user_id', 26)->nullable()->index('gondolas_user_id_index');
            $table->char('planogram_id', 26)->nullable()->index('gondolas_planogram_id_index');
            $table->char('linked_map_gondola_id', 26)->nullable();
            $table->string('linked_map_gondola_category')->nullable();
            $table->string('name');
            $table->string('slug')->nullable()->unique('gondolas_slug_unique');
            $table->integer('num_modulos')->default(1);
            $table->string('location')->nullable();
            $table->string('side')->nullable();
            $table->enum('flow', ['left_to_right', 'right_to_left'])->default('left_to_right');
            $table->enum('alignment', ['left', 'right', 'center', 'justify'])->default('justify');
            $table->float('scale_factor')->default(1)->comment('Fator de escala para visualização');
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
        Schema::dropIfExists('gondolas');
    }
};
