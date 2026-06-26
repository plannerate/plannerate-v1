<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modo de acesso por coluna do workflow: 'edit' (abre o editor) ou 'view'
 * (somente visualizar o PDF). Definido no template (coluna do Kanban) e,
 * opcionalmente, sobrescrito por etapa de planograma.
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::table('workflow_templates', function (Blueprint $table): void {
            $table->string('access_mode')->default('edit')->after('default_role_id');
        });

        Schema::table('workflow_planogram_steps', function (Blueprint $table): void {
            $table->string('access_mode')->nullable()->after('role_id');
        });

        // Backfill para tenants que já semearam os templates antes desta coluna
        // existir: da 4ª etapa (Aprovação comercial) em diante é somente leitura.
        DB::connection($this->connection)
            ->table('workflow_templates')
            ->where('suggested_order', '>=', 4)
            ->update(['access_mode' => 'view']);
    }

    public function down(): void
    {
        Schema::table('workflow_templates', function (Blueprint $table): void {
            $table->dropColumn('access_mode');
        });

        Schema::table('workflow_planogram_steps', function (Blueprint $table): void {
            $table->dropColumn('access_mode');
        });
    }
};
