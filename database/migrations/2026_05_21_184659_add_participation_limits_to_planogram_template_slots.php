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

        Schema::connection($this->connection)->table('planogram_template_slots', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_share_per_sku')->nullable()->after('visual_criteria');
            $table->unsignedSmallInteger('max_share_per_brand')->nullable()->after('max_share_per_sku');
            $table->unsignedSmallInteger('max_share_per_subcategory')->nullable()->after('max_share_per_brand');
        });
    }

    public function down(): void
    {
        if (DB::connection($this->connection)->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::connection($this->connection)->table('planogram_template_slots', function (Blueprint $table) {
            $table->dropColumn(['max_share_per_sku', 'max_share_per_brand', 'max_share_per_subcategory']);
        });
    }
};
