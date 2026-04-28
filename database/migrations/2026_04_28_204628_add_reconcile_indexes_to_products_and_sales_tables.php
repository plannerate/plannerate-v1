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
        Schema::table('products', function (Blueprint $table): void {
            $table->index(
                ['tenant_id', 'codigo_erp', 'deleted_at'],
                'products_tenant_codigo_erp_deleted_at_idx'
            );
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->index(
                ['tenant_id', 'codigo_erp'],
                'sales_tenant_codigo_erp_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_tenant_codigo_erp_deleted_at_idx');
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropIndex('sales_tenant_codigo_erp_idx');
        });
    }
};
