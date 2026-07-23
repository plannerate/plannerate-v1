<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_spaces', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->nullable()->index();
            $table->foreignUlid('user_id')->nullable()->index();
            $table->foreignUlid('store_id')->nullable()->index();
            $table->foreignUlid('prefix_id')->nullable()->index();
            $table->foreignUlid('category_id')->nullable()->index();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->string('type')->nullable()->comment('Slug do tipo de espaço');
            $table->string('prefix')->nullable();
            $table->string('space_number', 50)->nullable();
            $table->integer('height')->nullable();
            $table->integer('width')->nullable();
            $table->integer('depth')->nullable();
            $table->decimal('real_width', 8, 2)->nullable()->comment('Dimensão real em cm');
            $table->decimal('real_height', 8, 2)->nullable()->comment('Dimensão real em cm');
            $table->decimal('real_depth', 8, 2)->nullable()->comment('Dimensão real em cm');
            $table->decimal('price', 10, 2)->nullable();
            $table->string('price_period', 20)->default('week');
            $table->string('status', 30)->default('available');
            $table->text('block_reason')->nullable();
            $table->boolean('is_auditable')->default(false);
            $table->string('current_client')->nullable();
            $table->date('occupied_from')->nullable();
            $table->date('occupied_until')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'store_id']);
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_spaces');
    }
};
