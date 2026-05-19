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
            $table->dropColumn(['grouping', 'grouping_normalized']);
        });
    }

    public function down(): void
    {
        Schema::table('planogram_template_slots', function (Blueprint $table) {
            $table->string('grouping')->nullable()->comment('Agrupamento de exposição — col I');
            $table->string('grouping_normalized')->nullable()->comment('grouping em lowercase+trim');
        });
    }
};
