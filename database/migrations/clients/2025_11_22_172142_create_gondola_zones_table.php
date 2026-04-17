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
        Schema::create('gondola_zones', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('gondola_id', 26);
            $table->string('name');
            $table->longText('shelf_indexes');
            $table->decimal('performance_multiplier', 5, 2)->default(1.00);
            $table->longText('rules');
            $table->integer('order')->default(0)->index('gondola_zones_order_index');
            $table->timestamps();
            
            // Foreign key removida para suportar multi-database
            // gondola_id é mantido como ULID simples sem constraint
        }); 
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gondola_zones');
    }
};
