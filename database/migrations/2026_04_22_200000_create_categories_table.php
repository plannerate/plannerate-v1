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
        Schema::create('categories', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id')->nullable()->index();
            $table->ulid('user_id')->nullable();
            $table->ulid('category_id')->nullable();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('level_name')->nullable();
            $table->integer('codigo')->nullable();
            $table->enum('status', ['draft', 'published', 'importer'])->default('draft');
            $table->string('description')->nullable();
            $table->string('nivel')->nullable();
            $table->integer('hierarchy_position')->nullable();
            $table->text('full_path')->nullable();
            $table->json('hierarchy_path')->nullable();
            $table->boolean('is_placeholder')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'nivel']);
            $table->index(['tenant_id', 'hierarchy_position']);
            $table->index(['tenant_id', 'is_placeholder']);
            $table->index(['tenant_id', 'category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
