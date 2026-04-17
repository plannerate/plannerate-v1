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
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26)->nullable();
            $table->char('user_id', 26)->nullable()->index('segments_user_id_index');
            $table->char('shelf_id', 26)->nullable();
            $table->integer('width')->nullable();
            $table->decimal('distributed_width', 8, 2)->nullable()->comment("Largura calculada para distribuição em justify (em cm)");
            $table->integer('height')->nullable();
            $table->integer('ordering')->default(0);
            $table->enum('alignment', ['left', 'right', 'center', 'justify'])->nullable();
            $table->integer('position')->nullable();
            $table->integer('quantity')->default(1);
            $table->integer('spacing')->nullable();
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
        Schema::dropIfExists('segments');
    }
};
