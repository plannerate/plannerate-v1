<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::table('scoring_weights', function (Blueprint $table) {
            $table->decimal('vertical_block_threshold', 5, 2)
                ->default(0.20)
                ->after('adjacency_hierarchy_level')
                ->comment('Top % de score que recebe bloco vertical. Ex: 0.20 = top 20%');
            $table->unsignedTinyInteger('vertical_block_min_shelves')
                ->default(2)
                ->after('vertical_block_threshold')
                ->comment('Mínimo de prateleiras para aplicar bloco vertical');
        });
    }

    public function down(): void
    {
        Schema::table('scoring_weights', function (Blueprint $table) {
            $table->dropColumn(['vertical_block_threshold', 'vertical_block_min_shelves']);
        });
    }
};
