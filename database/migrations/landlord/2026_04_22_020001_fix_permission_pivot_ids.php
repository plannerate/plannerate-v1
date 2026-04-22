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
        $this->dropLegacyIdColumn('model_has_roles');
        $this->dropLegacyIdColumn('model_has_permissions');
    }

    public function down(): void
    {
        if (Schema::connection($this->connection)->hasTable('model_has_roles')
            && ! Schema::connection($this->connection)->hasColumn('model_has_roles', 'id')) {
            Schema::connection($this->connection)->table('model_has_roles', function (Blueprint $table): void {
                $table->ulid('id')->first();
            });
        }

        if (Schema::connection($this->connection)->hasTable('model_has_permissions')
            && ! Schema::connection($this->connection)->hasColumn('model_has_permissions', 'id')) {
            Schema::connection($this->connection)->table('model_has_permissions', function (Blueprint $table): void {
                $table->ulid('id')->first();
            });
        }
    }

    private function dropLegacyIdColumn(string $table): void
    {
        if (! Schema::connection($this->connection)->hasTable($table)
            || ! Schema::connection($this->connection)->hasColumn($table, 'id')) {
            return;
        }

        try {
            DB::connection($this->connection)->statement(sprintf('ALTER TABLE `%s` DROP PRIMARY KEY', $table));
        } catch (Throwable) {
            // Ignore when primary key was already removed by a previous hotfix.
        }

        Schema::connection($this->connection)->table($table, function (Blueprint $blueprint): void {
            $blueprint->dropColumn('id');
        });
    }
};
