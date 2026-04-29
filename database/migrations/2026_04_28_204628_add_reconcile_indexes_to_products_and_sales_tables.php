<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! $this->indexExists('products', 'products_tenant_codigo_erp_deleted_at_idx')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->index(
                    ['tenant_id', 'codigo_erp', 'deleted_at'],
                    'products_tenant_codigo_erp_deleted_at_idx'
                );
            });
        }

        if (! $this->indexExists('sales', 'sales_tenant_codigo_erp_idx')) {
            Schema::table('sales', function (Blueprint $table): void {
                $table->index(
                    ['tenant_id', 'codigo_erp'],
                    'sales_tenant_codigo_erp_idx'
                );
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->indexExists('products', 'products_tenant_codigo_erp_deleted_at_idx')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropIndex('products_tenant_codigo_erp_deleted_at_idx');
            });
        }

        if ($this->indexExists('sales', 'sales_tenant_codigo_erp_idx')) {
            Schema::table('sales', function (Blueprint $table): void {
                $table->dropIndex('sales_tenant_codigo_erp_idx');
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $result = $connection->select(
                sprintf('SHOW INDEX FROM `%s` WHERE Key_name = ?', str_replace('`', '``', $table)),
                [$indexName],
            );

            return $result !== [];
        }

        if ($driver === 'pgsql') {
            $result = $connection->select(
                'SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ? LIMIT 1',
                [$table, $indexName],
            );

            return $result !== [];
        }

        if ($driver === 'sqlite') {
            $indexes = $connection->select(
                sprintf('PRAGMA index_list("%s")', str_replace('"', '""', $table))
            );

            foreach ($indexes as $index) {
                if (($index->name ?? null) === $indexName) {
                    return true;
                }
            }
        }

        return false;
    }
};
