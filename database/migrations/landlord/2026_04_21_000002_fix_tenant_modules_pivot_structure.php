<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('tenant_modules')) {
            return;
        }

        if (! Schema::connection($this->connection)->hasColumn('tenant_modules', 'id')) {
            return;
        }

        Schema::connection($this->connection)->table('tenant_modules', function (Blueprint $table): void {
            $table->dropPrimary();
            $table->dropColumn('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::connection($this->connection)->hasTable('tenant_modules')) {
            return;
        }

        if (Schema::connection($this->connection)->hasColumn('tenant_modules', 'id')) {
            return;
        }

        Schema::connection($this->connection)->table('tenant_modules', function (Blueprint $table): void {
            $table->ulid('id')->first();
            $table->primary('id');
        });
    }
};
