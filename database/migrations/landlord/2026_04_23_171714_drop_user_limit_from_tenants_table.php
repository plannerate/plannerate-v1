<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('landlord')->table('tenants', function (Blueprint $table): void {
            $table->dropColumn('user_limit');
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->table('tenants', function (Blueprint $table): void {
            $table->unsignedInteger('user_limit')->nullable()->after('plan_id');
        });
    }
};
