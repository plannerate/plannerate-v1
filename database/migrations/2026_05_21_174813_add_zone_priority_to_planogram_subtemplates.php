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
            $table->string('hot_zone_priority')->nullable()->after('slot_defaults');
            $table->string('cold_zone_priority')->nullable()->after('hot_zone_priority');
        });
    }

    public function down(): void
    {
        if (DB::connection($this->connection)->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::connection($this->connection)->table('planogram_subtemplates', function (Blueprint $table): void {
            $table->dropColumn(['hot_zone_priority', 'cold_zone_priority']);
        });
    }
};
