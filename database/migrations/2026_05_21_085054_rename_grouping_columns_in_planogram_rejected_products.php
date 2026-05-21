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

        Schema::connection($this->connection)->table('planogram_rejected_products', function (Blueprint $table) {
            $table->renameColumn('grouping', 'category_name');
            $table->renameColumn('grouping_normalized', 'category_id');
        });
    }

    public function down(): void
    {
        if (DB::connection($this->connection)->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::connection($this->connection)->table('planogram_rejected_products', function (Blueprint $table) {
            $table->renameColumn('category_name', 'grouping');
            $table->renameColumn('category_id', 'grouping_normalized');
        });
    }
};
