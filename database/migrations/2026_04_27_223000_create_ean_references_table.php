<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ean_references', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id')->index();
            $table->string('ean', 32);
            $table->foreignUlid('category_id')->nullable()->constrained('categories')->nullOnDelete();

            $table->text('reference_description')->nullable();
            $table->string('brand')->nullable();
            $table->string('subbrand')->nullable();
            $table->string('packaging_type')->nullable();
            $table->string('packaging_size')->nullable();
            $table->string('measurement_unit')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'ean']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ean_references');
    }
};
