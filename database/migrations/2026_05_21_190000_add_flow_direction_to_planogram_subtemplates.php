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

        Schema::connection($this->connection)->table('planogram_subtemplates', function (Blueprint $table): void {
            $table->string('flow_direction')->nullable()->after('cold_zone_priority');
        });
    }

    public function down(): void
    {
        if (DB::connection($this->connection)->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::connection($this->connection)->table('planogram_subtemplates', function (Blueprint $table): void {
            $table->dropColumn('flow_direction');
        });
    }
};
