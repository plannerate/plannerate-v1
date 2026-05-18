<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::create('similar_groups', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id')->nullable()->index();
            $table->ulid('user_id')->nullable();

            $table->string('grouper_code')->index();
            $table->string('name');
            $table->json('product_codes')->default('[]');
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->text('description')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('similar_groups');
    }
};
