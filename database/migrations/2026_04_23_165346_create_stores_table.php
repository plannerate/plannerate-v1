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
        Schema::create('stores', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id')->nullable()->index();
            $table->ulid('user_id')->nullable()->index();
            $table->string('name')->nullable();
            $table->string('document')->nullable();
            $table->string('slug')->nullable();
            $table->string('code')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->string('description')->nullable();
            $table->string('map_image_path')->nullable();
            $table->json('map_regions')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
