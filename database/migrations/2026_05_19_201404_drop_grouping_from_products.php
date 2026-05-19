<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('products', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'grouping_normalized']);
            $table->dropColumn(['grouping', 'grouping_normalized']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('products', function (Blueprint $table) {
            $table->string('grouping')->nullable()->after('name');
            $table->string('grouping_normalized')->nullable()->after('grouping');
            $table->index(['tenant_id', 'grouping_normalized']);
        });
    }
};
