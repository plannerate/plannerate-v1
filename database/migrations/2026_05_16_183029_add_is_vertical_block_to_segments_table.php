<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::table('segments', function (Blueprint $table) {
            $table->boolean('is_vertical_block')
                ->default(false)
                ->after('quantity')
                ->comment('Segmento gerado pelo VerticalBlockPlacer (mesma posição X em múltiplas prateleiras)');
        });
    }

    public function down(): void
    {
        Schema::table('segments', function (Blueprint $table) {
            $table->dropColumn('is_vertical_block');
        });
    }
};
