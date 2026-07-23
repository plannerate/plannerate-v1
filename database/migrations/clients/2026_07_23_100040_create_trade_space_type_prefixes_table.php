<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_space_type_prefixes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('space_type_id')->index();
            $table->foreignUlid('space_prefix_id')->index();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['space_type_id', 'space_prefix_id']);
            $table->index(['space_type_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_space_type_prefixes');
    }
};
