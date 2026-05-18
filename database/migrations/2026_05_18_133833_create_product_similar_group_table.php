<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::create('product_similar_group', function (Blueprint $table): void {
            $table->ulid('tenant_id')->nullable()->index();
            $table->foreignUlid('similar_group_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['similar_group_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_similar_group');
    }
};
