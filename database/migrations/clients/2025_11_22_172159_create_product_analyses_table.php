<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_analyses', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('product_id')->index('product_analyses_product_id_index');
            $table->string('product_name');
            $table->decimal('total_sales', 15, 2)->default(0.00);
            $table->decimal('average_sales', 10, 4)->default(0.0000);
            $table->decimal('standard_deviation', 10, 4)->default(0.0000);
            $table->decimal('sales_variability', 5, 4)->default(0.0000);
            $table->enum('abc_classification', ['a', 'b', 'c'])->default('B')->index('product_analyses_abc_classification_index');
            $table->integer('sales_rank')->default(0);
            $table->decimal('cumulative_percentage', 5, 2)->default(0.00);
            $table->decimal('service_level', 3, 2)->default(0.90);
            $table->decimal('z_score', 6, 4)->default(0.0000);
            $table->integer('safety_stock')->default(0);
            $table->integer('minimum_stock')->default(0);
            $table->integer('target_stock')->default(0);
            $table->integer('coverage_days')->default(14);
            $table->boolean('allows_facing')->default(1);
            $table->integer('current_stock')->default(0);
            $table->date('last_sale_date')->nullable();
            $table->timestamp('analysis_date')->useCurrent()->index('product_analyses_analysis_date_index');
            $table->timestamps();
            
            $table->unique(['product_id', 'analysis_date'], 'product_analyses_product_id_analysis_date_unique');
            $table->index(['product_id', 'analysis_date'], 'product_analyses_product_id_analysis_date_index');
            $table->index(['abc_classification', 'analysis_date'], 'product_analyses_abc_classification_analysis_date_index');
        }); 
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_analyses');
    }
};
