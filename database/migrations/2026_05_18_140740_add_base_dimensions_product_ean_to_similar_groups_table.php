<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::table('similar_groups', function (Blueprint $table): void {
            $table->string('base_dimensions_product_ean')->nullable()->after('product_codes')->index();
        });
    }

    public function down(): void
    {
        Schema::table('similar_groups', function (Blueprint $table): void {
            $table->dropColumn('base_dimensions_product_ean');
        });
    }
};
