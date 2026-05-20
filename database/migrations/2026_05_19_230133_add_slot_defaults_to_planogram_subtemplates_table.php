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
        Schema::table('planogram_subtemplates', function (Blueprint $table) {
            $table->json('slot_defaults')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planogram_subtemplates', function (Blueprint $table) {
            $table->dropColumn('slot_defaults');
        });
    }
};
