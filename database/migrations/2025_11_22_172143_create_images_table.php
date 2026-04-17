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
        Schema::create('images', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26)->nullable();
            $table->char('user_id', 26)->nullable()->index('images_user_id_index');
            $table->string('path')->nullable();
            $table->string('name');
            $table->string('slug')->unique('images_slug_unique');
            $table->string('extension', 10)->nullable();
            $table->string('mime_type')->nullable();
            $table->uuid('size')->nullable();
            $table->string('disk')->default('public');
            $table->uuid('width')->nullable();
            $table->uuid('height')->nullable();
            $table->string('alt_text')->nullable();
            $table->enum('orintation', ['left', 'right', 'front'])->default('front');
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
        Schema::dropIfExists('images');
    }
};
