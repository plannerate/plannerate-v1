<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->table('planogram_template_slots', function (Blueprint $table) {
            $table->enum('facing_expansion', ['none', 'score', 'current_stock', 'equal'])
                ->default('none')
                ->after('use_target_stock')
                ->comment('Estratégia de expansão de frentes além do min_facings');
            $table->unsignedSmallInteger('max_facings')
                ->default(5)
                ->after('facing_expansion')
                ->comment('Máximo de frentes por SKU (ceiling da expansão)');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('planogram_template_slots', function (Blueprint $table) {
            $table->dropColumn(['facing_expansion', 'max_facings']);
        });
    }
};
