<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aposentadoria dos blocos verticais (decisão 2026-06-11):
 * a feature não foi portada ao TemplatePlacementEngine no reroute do modo
 * automático e nunca produziu um bloco em produção (0 usos em 84k+ segments
 * nos 6 tenants). As superfícies de código foram removidas nos commits
 * 9bde289 e seguintes; esta migration remove as colunas remanescentes.
 *
 * Spec para eventual reimplementação: histórico git do antigo
 * tests/Feature/AutoPlanogramVerticalBlockTest.php.
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasColumn('segments', 'is_vertical_block')) {
            Schema::connection($this->connection)->table('segments', function (Blueprint $table) {
                $table->dropColumn('is_vertical_block');
            });
        }

        if (Schema::connection($this->connection)->hasColumn('scoring_weights', 'vertical_block_threshold')) {
            Schema::connection($this->connection)->table('scoring_weights', function (Blueprint $table) {
                $table->dropColumn('vertical_block_threshold');
            });
        }

        if (Schema::connection($this->connection)->hasColumn('scoring_weights', 'vertical_block_min_shelves')) {
            Schema::connection($this->connection)->table('scoring_weights', function (Blueprint $table) {
                $table->dropColumn('vertical_block_min_shelves');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::connection($this->connection)->hasColumn('segments', 'is_vertical_block')) {
            Schema::connection($this->connection)->table('segments', function (Blueprint $table) {
                $table->boolean('is_vertical_block')->default(false);
            });
        }

        if (! Schema::connection($this->connection)->hasColumn('scoring_weights', 'vertical_block_threshold')) {
            Schema::connection($this->connection)->table('scoring_weights', function (Blueprint $table) {
                $table->float('vertical_block_threshold')->default(0.20);
                $table->unsignedTinyInteger('vertical_block_min_shelves')->default(2);
            });
        }
    }
};
