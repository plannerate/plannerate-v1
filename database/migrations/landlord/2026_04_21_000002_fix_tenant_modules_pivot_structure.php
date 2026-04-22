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
        if (! Schema::connection('landlord')->hasTable('tenant_modules')) {
            return;
        }

        if (! Schema::connection('landlord')->hasColumn('tenant_modules', 'id')) {
            return;
        }

        Schema::connection('landlord')->table('tenant_modules', function (Blueprint $table): void {
            $table->dropPrimary();
            $table->dropColumn('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::connection('landlord')->hasTable('tenant_modules')) {
            return;
        }

        if (Schema::connection('landlord')->hasColumn('tenant_modules', 'id')) {
            return;
        }

        Schema::connection('landlord')->table('tenant_modules', function (Blueprint $table): void {
            $table->ulid('id')->first();
            $table->primary('id');
        });
    }
};
