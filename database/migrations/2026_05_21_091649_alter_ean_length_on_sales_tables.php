<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        if (DB::connection($this->connection)->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::connection($this->connection)->table('sales', function (Blueprint $table) {
            $table->string('ean', 255)->nullable()->change();
        });

        Schema::connection($this->connection)->table('monthly_sales_summaries', function (Blueprint $table) {
            $table->string('ean', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::connection($this->connection)->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::connection($this->connection)->table('sales', function (Blueprint $table) {
            $table->string('ean', 13)->nullable()->change();
        });

        Schema::connection($this->connection)->table('monthly_sales_summaries', function (Blueprint $table) {
            $table->string('ean', 13)->nullable()->change();
        });
    }
};
