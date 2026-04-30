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
        Schema::table('products', function (Blueprint $table) {
            $table->string('resolution_status', 40)
                ->nullable()
                ->after('sync_source')
                ->index();
            $table->json('resolution_details')
                ->nullable()
                ->after('resolution_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['resolution_status']);
            $table->dropColumn('resolution_details');
            $table->dropColumn('resolution_status');
        });
    }
};
