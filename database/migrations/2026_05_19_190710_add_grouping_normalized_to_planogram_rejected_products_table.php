<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('planogram_rejected_products', function (Blueprint $table) {
            $table->string('grouping_normalized')->nullable()->after('grouping')->comment('Normalized grouping for filtering');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planogram_rejected_products', function (Blueprint $table) {
            $table->dropColumn('grouping_normalized');
        });
    }
};
