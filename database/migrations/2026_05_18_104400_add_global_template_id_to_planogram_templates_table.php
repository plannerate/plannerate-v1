<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::table('planogram_templates', function (Blueprint $table) {
            $table->char('global_template_id', 26)->nullable()->after('tenant_id')
                ->comment('Referência ao template global de origem (quando copiado do landlord)');
        });
    }

    public function down(): void
    {
        Schema::table('planogram_templates', function (Blueprint $table) {
            $table->dropColumn('global_template_id');
        });
    }
};
