<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::table('scoring_weights', function (Blueprint $table) {
            $table->unsignedTinyInteger('block_hierarchy_level')->default(6)->after('sales_window_months');
            $table->unsignedTinyInteger('adjacency_hierarchy_level')->default(4)->after('block_hierarchy_level');
        });
    }

    public function down(): void
    {
        Schema::table('scoring_weights', function (Blueprint $table) {
            $table->dropColumn(['block_hierarchy_level', 'adjacency_hierarchy_level']);
        });
    }
};
