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
            $table->dropColumn(['category', 'subcategory']);
        });
    }

    public function down(): void
    {
        Schema::table('planogram_template_slots', function (Blueprint $table) {
            $table->string('category')->nullable()->comment('Categoria — col G');
            $table->string('subcategory')->nullable()->comment('Subcategoria — col H');
        });
    }
};
