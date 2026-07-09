<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    /**
     * Marca quais perfis (roles) são "administrativos" e, portanto, sujeitos a
     * limite de quantidade de usuários por plano. A flag é editável na tela de
     * Perfis; o valor do limite de cada perfil vive nos plan_items do plano.
     */
    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('roles')) {
            return;
        }

        if (! Schema::connection($this->connection)->hasColumn('roles', 'is_administrative')) {
            Schema::connection($this->connection)->table('roles', function (Blueprint $table): void {
                $table->boolean('is_administrative')->default(false)->after('name');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::connection($this->connection)->hasTable('roles')) {
            return;
        }

        if (Schema::connection($this->connection)->hasColumn('roles', 'is_administrative')) {
            Schema::connection($this->connection)->table('roles', function (Blueprint $table): void {
                $table->dropColumn('is_administrative');
            });
        }
    }
};
