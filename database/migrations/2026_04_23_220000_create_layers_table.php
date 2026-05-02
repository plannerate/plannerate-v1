<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected string $connection = 'tenant';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('layers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->nullable()->index();
            $table->foreignUlid('user_id')->nullable()->index();
            $table->foreignUlid('segment_id')->nullable()->index();
            $table->foreignUlid('gondola_id')->nullable()->index();
            $table->foreignUlid('product_id')->nullable()->index();
            $table->decimal('height', 8, 2)->nullable()->comment('Altura do layer em cm');
            $table->decimal('distributed_width', 8, 2)->nullable()->comment('Largura calculada para distribuição em justify (em cm)');
            $table->integer('quantity')->default(1);
            $table->enum('alignment', ['left', 'right', 'center', 'justify'])->nullable();
            $table->integer('spacing')->default(0);
            $table->longText('settings')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('layers');
    }
};
