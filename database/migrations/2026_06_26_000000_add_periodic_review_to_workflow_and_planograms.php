<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revisão Periódica automática + conclusão na etapa Execução Loja.
 *
 * Introduz `stage_type` (flow|periodic_review) no template e na etapa, e o
 * ciclo de vida de fluxo do planograma (`lifecycle_status` + timestamps de
 * conclusão/vencimento/início de revisão). Backfill apenas estrutural — não
 * altera planogramas/execuções já finalizados (decisão não-retroativa).
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::table('workflow_templates', function (Blueprint $table): void {
            $table->string('stage_type')->default('flow')->after('access_mode');
        });

        Schema::table('workflow_planogram_steps', function (Blueprint $table): void {
            $table->string('stage_type')->nullable()->after('access_mode');
        });

        Schema::table('planograms', function (Blueprint $table): void {
            $table->string('lifecycle_status')->default('in_progress')->after('status');
            $table->timestamp('completed_at')->nullable()->after('lifecycle_status');
            $table->timestamp('periodic_review_due_at')->nullable()->after('completed_at');
            $table->timestamp('periodic_review_started_at')->nullable()->after('periodic_review_due_at');

            $table->index(['lifecycle_status', 'periodic_review_due_at'], 'planograms_lifecycle_due_index');
        });

        // Backfill estrutural: etapas a partir da ordem 7 (Revisão Periódica)
        // passam a ser do tipo periodic_review; as demais ficam 'flow' pelo
        // default. Planogramas permanecem 'in_progress' (default) — nada de
        // retroativo em conclusões/execuções já existentes.
        DB::connection($this->connection)
            ->table('workflow_templates')
            ->where('suggested_order', '>=', 7)
            ->update(['stage_type' => 'periodic_review']);
    }

    public function down(): void
    {
        Schema::table('planograms', function (Blueprint $table): void {
            $table->dropIndex('planograms_lifecycle_due_index');
            $table->dropColumn([
                'lifecycle_status',
                'completed_at',
                'periodic_review_due_at',
                'periodic_review_started_at',
            ]);
        });

        Schema::table('workflow_planogram_steps', function (Blueprint $table): void {
            $table->dropColumn('stage_type');
        });

        Schema::table('workflow_templates', function (Blueprint $table): void {
            $table->dropColumn('stage_type');
        });
    }
};
