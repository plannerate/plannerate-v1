<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        Schema::create('ean_references', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('ean', 32)->unique();
            $table->ulid('category_id')->nullable()->index();
            $table->string('category_name')->nullable();
            $table->string('category_slug')->nullable()->index();

            $table->text('reference_description')->nullable();
            $table->string('brand')->nullable();
            $table->string('subbrand')->nullable();
            $table->string('packaging_type')->nullable();
            $table->string('packaging_size')->nullable();
            $table->string('measurement_unit')->nullable();

            $table->decimal('width', 10, 2)->nullable();
            $table->decimal('height', 10, 2)->nullable();
            $table->decimal('depth', 10, 2)->nullable();
            $table->decimal('weight', 10, 2)->nullable();
            $table->string('unit')->default('cm');
            $table->boolean('has_dimensions')->default(false);
            $table->enum('dimension_status', ['draft', 'published'])->default('published');

            $table->string('image_front_url')->nullable();
            $table->string('image_side_url')->nullable();
            $table->string('image_top_url')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['brand', 'subbrand']);
            $table->index(['dimension_status', 'has_dimensions']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ean_references');
    }
};
