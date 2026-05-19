<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::table('planogram_template_slots', function (Blueprint $table) {
            $table->char('category_id', 26)->nullable()
                ->after('subtemplate_id')
                ->comment('FK → categories. Substitui grouping_normalized. Suporta hierarquia recursiva.');
            $table->index(['tenant_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::table('planogram_template_slots', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'category_id']);
            $table->dropColumn('category_id');
        });
    }
};
