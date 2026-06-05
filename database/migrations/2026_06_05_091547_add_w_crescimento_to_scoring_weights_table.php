<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::table('scoring_weights', function (Blueprint $table): void {
            $table->decimal('w_crescimento', 5, 2)->default(0.00)->after('w_doh');
        });
    }

    public function down(): void
    {
        Schema::table('scoring_weights', function (Blueprint $table): void {
            $table->dropColumn('w_crescimento');
        });
    }
};
