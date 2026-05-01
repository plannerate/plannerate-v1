<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('landlord')->create('plan_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->string('key', 100);
            $table->string('label');
            $table->string('value', 500)->nullable();
            $table->enum('type', ['integer', 'boolean', 'string'])->default('string');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['plan_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('plan_items');
    }
};
