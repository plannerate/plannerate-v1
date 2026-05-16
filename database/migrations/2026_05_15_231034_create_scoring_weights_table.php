<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::create('scoring_weights', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->decimal('w_giro', 5, 2)->default(0.40);
            $table->decimal('w_margem', 5, 2)->default(0.30);
            $table->decimal('w_estrategico', 5, 2)->default(0.20);
            $table->decimal('w_doh', 5, 2)->default(0.10);
            $table->unsignedTinyInteger('sales_window_months')->default(4);
            $table->timestamps();
            $table->softDeletes();
            $table->unique('tenant_id');
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scoring_weights');
    }
};
