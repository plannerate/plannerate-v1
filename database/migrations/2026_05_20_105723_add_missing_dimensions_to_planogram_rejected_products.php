<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        // SQLite (tests) does not support CHECK constraint alteration — skip
        if (DB::connection($this->connection)->getDriverName() !== 'pgsql') {
            return;
        }

        DB::connection($this->connection)->statement(
            'ALTER TABLE planogram_rejected_products DROP CONSTRAINT IF EXISTS planogram_rejected_products_rejection_reason_check'
        );

        DB::connection($this->connection)->statement(
            "ALTER TABLE planogram_rejected_products ADD CONSTRAINT planogram_rejected_products_rejection_reason_check
             CHECK (rejection_reason IN ('no_horizontal_space','height_exceeds_shelf','no_shelf_at_level','missing_dimensions'))"
        );
    }

    public function down(): void
    {
        if (DB::connection($this->connection)->getDriverName() !== 'pgsql') {
            return;
        }

        DB::connection($this->connection)->statement(
            'ALTER TABLE planogram_rejected_products DROP CONSTRAINT IF EXISTS planogram_rejected_products_rejection_reason_check'
        );

        DB::connection($this->connection)->statement(
            "ALTER TABLE planogram_rejected_products ADD CONSTRAINT planogram_rejected_products_rejection_reason_check
             CHECK (rejection_reason IN ('no_horizontal_space','height_exceeds_shelf','no_shelf_at_level'))"
        );
    }
};
