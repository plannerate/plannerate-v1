<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Propostas de reotimização: o que a gôndola SERIA se fosse regerada com os dados de venda de hoje.
 *
 * A geração de planograma é destrutiva (o writer apaga e recria todos os segments). Reprocessar
 * periodicamente e gravar direto apagaria o trabalho manual do usuário sem aviso. Então o
 * reprocessamento roda em dry-run e o resultado vira uma PROPOSTA: o usuário vê o diff e decide.
 *
 * `proposed_layout` guarda o snapshot COMPLETO do layout calculado — aprovar aplica exatamente
 * aquele snapshot, sem recalcular. É o que garante que o que o usuário revisou é o que entra na
 * gôndola, mesmo que as vendas mudem entre a proposta e a aprovação.
 *
 * `baseline_hash` é a assinatura do layout que existia quando o diff foi montado. Se alguém editar
 * a gôndola no meio-tempo, o hash diverge e a aprovação é recusada (status `superseded`) — o diff
 * revisado descreveria uma realidade que não existe mais.
 *
 * Rodar via: docker compose exec php php artisan tenants:artisan "migrate --database=tenant"
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->create('planogram_reoptimization_proposals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->nullable()->index();
            $table->foreignUlid('planogram_id');
            $table->foreignUlid('gondola_id')->index();
            $table->foreignUlid('generation_run_id')->nullable()
                ->comment('Run (kind=proposal) que calculou esta proposta');
            $table->foreignUlid('applied_run_id')->nullable()
                ->comment('Run (kind=apply) criado ao aprovar, para o histórico ficar coerente');

            $table->string('status', 20)->default('pending')
                ->comment('pending | applied | rejected | no_changes | superseded | failed');
            $table->string('trigger', 20)->default('scheduled')
                ->comment('manual = "analisar agora" | scheduled = agendador');

            $table->json('config_snapshot')->nullable()
                ->comment('AutoGenerateConfigDTO usado no dry-run — para auditar com que regras a proposta nasceu');

            $table->json('baseline_layout')->nullable()->comment('Layout da gôndola ANTES (LayoutSnapshotSerializer)');
            $table->string('baseline_hash', 64)->nullable()->comment('sha256 do baseline — detector de staleness');
            $table->json('proposed_layout')->nullable()->comment('Layout calculado no dry-run; é ESTE que a aprovação aplica');
            $table->json('proposed_rejected')->nullable()->comment('Linhas de planogram_rejected_products propostas');

            $table->json('diff_summary')->nullable()->comment('LayoutDiff serializado — o que a tela mostra sem carregar os layouts');

            $table->date('sales_period_start')->nullable();
            $table->date('sales_period_end')->nullable();

            $table->decimal('occupancy_before', 5, 4)->nullable();
            $table->decimal('occupancy_after', 5, 4)->nullable();

            $table->foreignUlid('requested_by')->nullable()->comment('null = disparada pelo agendador');
            $table->foreignUlid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['gondola_id', 'status']);
            $table->index(['tenant_id', 'status']);
        });

        // Uma proposta pendente por gôndola. É invariante de negócio, não conveniência de UI: duas
        // propostas pendentes partiriam do mesmo baseline e aprovar as duas aplicaria a segunda por
        // cima da primeira, com o usuário achando que aplicou as duas.
        // SQLite (testes) não suporta índice parcial com WHERE — lá a garantia fica só no scheduler.
        if (DB::connection($this->connection)->getDriverName() !== 'pgsql') {
            return;
        }

        DB::connection($this->connection)->statement(
            "CREATE UNIQUE INDEX IF NOT EXISTS planogram_reopt_one_pending_per_gondola
             ON planogram_reoptimization_proposals (gondola_id)
             WHERE status = 'pending' AND deleted_at IS NULL"
        );
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('planogram_reoptimization_proposals');
    }
};
