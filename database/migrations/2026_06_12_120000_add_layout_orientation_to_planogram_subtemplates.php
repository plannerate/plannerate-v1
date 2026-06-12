<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Disposição dos produtos nos módulos: 'horizontal' (legado, default via null)
 * ou 'vertical' (blocagem por marca — colunas alinhadas entre prateleiras).
 *
 * Guard pgsql: o SQLite dos testes cria o schema inline nos próprios testes.
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        if (DB::connection($this->connection)->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::connection($this->connection)->table('planogram_subtemplates', function (Blueprint $table): void {
            $table->string('layout_orientation')->nullable()->after('flow_direction');
        });
    }

    public function down(): void
    {
        if (DB::connection($this->connection)->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::connection($this->connection)->table('planogram_subtemplates', function (Blueprint $table): void {
            $table->dropColumn('layout_orientation');
        });
    }
};
