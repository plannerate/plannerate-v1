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
        if (Schema::connection('landlord')->hasTable('tenants')) {
            return;
        }

        Schema::connection('landlord')->create('tenants', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('domain')->unique();
            $table->string('database')->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::connection('landlord')->hasTable('tenants')) {
            return;
        }

        if (Schema::connection('landlord')->hasColumn('tenants', 'domain')) {
            Schema::connection('landlord')->dropIfExists('tenants');
        }
    }
};
