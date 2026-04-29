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
        Schema::table('sales', function (Blueprint $table) {
            $table->index(['tenant_id', 'sale_date'], 'sales_tenant_sale_date_idx');
            $table->index(['tenant_id', 'store_id', 'sale_date'], 'sales_tenant_store_sale_date_idx');
            $table->index(['tenant_id', 'total_sale_quantity'], 'sales_tenant_total_sale_quantity_idx');
            $table->index(['tenant_id', 'total_sale_value'], 'sales_tenant_total_sale_value_idx');
            $table->index(['tenant_id', 'codigo_erp'], 'sales_tenant_codigo_erp_order_idx');
            $table->index(['tenant_id', 'store_id', 'total_sale_quantity'], 'sales_tenant_store_total_sale_quantity_idx');
            $table->index(['tenant_id', 'store_id', 'total_sale_value'], 'sales_tenant_store_total_sale_value_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('sales_tenant_sale_date_idx');
            $table->dropIndex('sales_tenant_store_sale_date_idx');
            $table->dropIndex('sales_tenant_total_sale_quantity_idx');
            $table->dropIndex('sales_tenant_total_sale_value_idx');
            $table->dropIndex('sales_tenant_codigo_erp_order_idx');
            $table->dropIndex('sales_tenant_store_total_sale_quantity_idx');
            $table->dropIndex('sales_tenant_store_total_sale_value_idx');
        });
    }
};
