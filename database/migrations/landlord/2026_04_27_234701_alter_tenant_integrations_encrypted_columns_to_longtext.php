<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::connection('landlord')->hasTable('tenant_integrations')) {
            return;
        }

        if (DB::connection('landlord')->getDriverName() !== 'mysql') {
            return;
        }

        DB::connection('landlord')->statement(
            'ALTER TABLE tenant_integrations
             MODIFY authentication_headers LONGTEXT NULL,
             MODIFY authentication_body LONGTEXT NULL,
             MODIFY config LONGTEXT NULL'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::connection('landlord')->hasTable('tenant_integrations')) {
            return;
        }

        if (DB::connection('landlord')->getDriverName() !== 'mysql') {
            return;
        }

        DB::connection('landlord')->statement(
            'ALTER TABLE tenant_integrations
             MODIFY authentication_headers JSON NULL,
             MODIFY authentication_body JSON NULL,
             MODIFY config JSON NULL'
        );
    }
};
