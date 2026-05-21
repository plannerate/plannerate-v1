<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        if (DB::connection($this->connection)->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::connection($this->connection)->table('categories', function (Blueprint $table): void {
            $table->string('role')->nullable()->after('hierarchy_position')
                ->comment('Papel mercadológico: destino, rotina, conveniencia, impulso, sazonal, complementar');
        });

        Schema::connection($this->connection)->table('planogram_template_slots', function (Blueprint $table): void {
            $table->string('role_override')->nullable()->after('facing_expansion')
                ->comment('Sobrepõe o papel da categoria para este slot específico');
        });
    }

    public function down(): void
    {
        if (DB::connection($this->connection)->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::connection($this->connection)->table('categories', function (Blueprint $table): void {
            $table->dropColumn('role');
        });

        Schema::connection($this->connection)->table('planogram_template_slots', function (Blueprint $table): void {
            $table->dropColumn('role_override');
        });
    }
};
