<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->index('gondola_id');
        });

        Schema::table('shelves', function (Blueprint $table) {
            $table->index('section_id');
        });

        Schema::table('segments', function (Blueprint $table) {
            $table->index('shelf_id');
        });

        Schema::table('layers', function (Blueprint $table) {
            $table->index('segment_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropIndex(['gondola_id']);
        });

        Schema::table('shelves', function (Blueprint $table) {
            $table->dropIndex(['section_id']);
        });

        Schema::table('segments', function (Blueprint $table) {
            $table->dropIndex(['shelf_id']);
        });

        Schema::table('layers', function (Blueprint $table) {
            $table->dropIndex(['segment_id']);
            $table->dropIndex(['product_id']);
        });
    }
};
