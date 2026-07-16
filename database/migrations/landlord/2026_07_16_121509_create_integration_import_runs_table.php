<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rastreia cada ciclo de importação (discover → fetch → process) com o PLANO
 * esperado + o que foi persistido, para detectar import parcial e dar
 * proveniência — sem contador de conclusão "ao vivo" (que daria falso-completo
 * sob autoPage/daily-mode; ver design no PR).
 *
 * Fluxo: discover grava o plano (status=running) → process acumula
 * persisted_records → sync:post-import, DEPOIS da barreira de filas vazias,
 * reconcilia a cobertura e marca complete/partial.
 *
 * Landlord: os jobs de discover/fetch/process são NotTenantAware; registro
 * central evita troca de contexto de tenant só para gravar progresso.
 */
return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        Schema::connection($this->connection)->create('integration_import_runs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id')->index();
            $table->ulid('integration_id');
            $table->string('path_key');
            $table->ulid('store_id')->nullable();
            $table->string('mode', 20)->comment('page | daily');
            $table->date('reference_date')->comment('Dia lógico do run (data do despacho)');

            // Plano gravado pelo discover.
            $table->unsignedInteger('expected_units')->default(0)->comment('Dias (daily) ou páginas (page) planejados');
            $table->json('expected_dates')->nullable()->comment('daily: lista de dias esperados, para reconciliar cobertura');
            $table->boolean('force_full')->default(false);

            // Acumulado pelo process (atômico).
            $table->unsignedBigInteger('persisted_records')->default(0);

            // Preenchido na reconciliação do sync:post-import.
            $table->unsignedInteger('covered_units')->nullable()->comment('Dias/páginas com dado confirmado na reconciliação');
            $table->string('status', 20)->default('running')->comment('running | complete | partial | failed');

            $table->timestamp('discovered_at')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();

            // Um run por ciclo de discover para a mesma chave lógica → o discover
            // reabre/atualiza em vez de duplicar quando re-roda no mesmo dia.
            $table->unique(['integration_id', 'path_key', 'store_id', 'reference_date'], 'import_runs_logical_unique');
            // Barreira/reconciliação: runs do dia por tenant e status.
            $table->index(['tenant_id', 'reference_date', 'status'], 'import_runs_barrier_idx');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('integration_import_runs');
    }
};
