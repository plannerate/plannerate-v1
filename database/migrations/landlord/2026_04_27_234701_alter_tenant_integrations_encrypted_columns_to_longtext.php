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

        $driver = DB::connection('landlord')->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::connection('landlord')->statement(
                'ALTER TABLE tenant_integrations
                 MODIFY authentication_headers LONGTEXT NULL,
                 MODIFY authentication_body LONGTEXT NULL,
                 MODIFY config LONGTEXT NULL'
            );

            return;
        }

        if ($driver === 'pgsql') {
            DB::connection('landlord')->statement(
                'ALTER TABLE tenant_integrations
                 ALTER COLUMN authentication_headers TYPE TEXT USING authentication_headers::text,
                 ALTER COLUMN authentication_body TYPE TEXT USING authentication_body::text,
                 ALTER COLUMN config TYPE TEXT USING config::text'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::connection('landlord')->hasTable('tenant_integrations')) {
            return;
        }

        $driver = DB::connection('landlord')->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::connection('landlord')->statement(
                'ALTER TABLE tenant_integrations
                 MODIFY authentication_headers JSON NULL,
                 MODIFY authentication_body JSON NULL,
                 MODIFY config JSON NULL'
            );

            return;
        }

        if ($driver === 'pgsql') {
            DB::connection('landlord')->statement(
                'ALTER TABLE tenant_integrations
                 ALTER COLUMN authentication_headers TYPE JSON USING authentication_headers::json,
                 ALTER COLUMN authentication_body TYPE JSON USING authentication_body::json,
                 ALTER COLUMN config TYPE JSON USING config::json'
            );
        }
    }
};
