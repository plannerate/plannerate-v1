<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::table('planograms', function (Blueprint $table) {
            $table->char('subtemplate_id', 26)->nullable()->after('id')
                ->comment('FK → planogram_subtemplates. null = modo automático.');
            $table->index('subtemplate_id');
        });
    }

    public function down(): void
    {
        Schema::table('planograms', function (Blueprint $table) {
            $table->dropIndex(['subtemplate_id']);
            $table->dropColumn('subtemplate_id');
        });
    }
};
