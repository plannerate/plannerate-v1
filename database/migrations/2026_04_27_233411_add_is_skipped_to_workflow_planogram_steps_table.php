<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_planogram_steps', function (Blueprint $table) {
            $table->boolean('is_skipped')->default(false)->after('is_required');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_planogram_steps', function (Blueprint $table) {
            $table->dropColumn('is_skipped');
        });
    }
};
