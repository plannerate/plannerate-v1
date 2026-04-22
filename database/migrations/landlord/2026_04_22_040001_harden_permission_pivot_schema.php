<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        $this->normalizePivotIdColumn('model_has_roles');
        $this->normalizePivotIdColumn('model_has_permissions');
    }

    public function down(): void
    {
        // no-op
    }

    private function normalizePivotIdColumn(string $table): void
    {
        if (! Schema::connection($this->connection)->hasTable($table)
            || ! Schema::connection($this->connection)->hasColumn($table, 'id')) {
            return;
        }

        try {
            DB::connection($this->connection)->statement(sprintf('ALTER TABLE `%s` DROP PRIMARY KEY', $table));
        } catch (Throwable) {
            // Ignore when there is no primary key on the legacy id column.
        }

        try {
            Schema::connection($this->connection)->table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('id');
            });

            return;
        } catch (Throwable) {
            // If we cannot drop it in this engine version, keep it nullable to avoid insert failures.
        }

        try {
            DB::connection($this->connection)->statement(sprintf('ALTER TABLE `%s` MODIFY `id` CHAR(26) NULL', $table));
        } catch (Throwable) {
            // no-op fallback
        }
    }
};
