<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('layers', function (Blueprint $table) {
            $table->foreignUlid('gondola_id')->nullable()->index()->after('segment_id');
        });

        // Backfill: popula gondola_id a partir da cadeia segment→shelf→section
        DB::statement('
            UPDATE layers l
            JOIN segments s  ON s.id  = l.segment_id
            JOIN shelves  sh ON sh.id = s.shelf_id
            JOIN sections sc ON sc.id = sh.section_id
            SET l.gondola_id = sc.gondola_id
            WHERE l.gondola_id IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('layers', function (Blueprint $table) {
            $table->dropIndex(['gondola_id']);
            $table->dropColumn('gondola_id');
        });
    }
};
