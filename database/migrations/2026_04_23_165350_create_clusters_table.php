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
        Schema::create('clusters', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('store_id')->index();
            $table->ulid('tenant_id')->nullable()->index();
            $table->ulid('user_id')->nullable()->index();
            $table->string('name');
            $table->string('specification_1')->nullable();
            $table->string('specification_2')->nullable();
            $table->string('specification_3')->nullable();
            $table->string('slug')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->string('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clusters');
    }
};
