<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::create('product_strategic_flags', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('product_id', 26);
            $table->boolean('is_strategic')->default(false);
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'product_id']);
            $table->index(['tenant_id', 'is_strategic']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_strategic_flags');
    }
};
