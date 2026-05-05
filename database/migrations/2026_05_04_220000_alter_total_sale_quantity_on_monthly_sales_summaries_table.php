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
        Schema::table('monthly_sales_summaries', function (Blueprint $table) {
            $table->decimal('total_sale_quantity', 12, 3)
                ->nullable()
                ->comment('Soma da quantidade vendida no mes')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monthly_sales_summaries', function (Blueprint $table) {
            $table->integer('total_sale_quantity')
                ->nullable()
                ->comment('Soma da quantidade vendida no mes')
                ->change();
        });
    }
};
