<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3 da extração do motor de integrações: remove `integration_sync_days`.
 *
 * Legado morto. O controle de cobertura por dia passou a ser derivado da própria
 * tabela alvo (`DailyModeDiscoverer::resolveMissingDays` calcula os dias faltantes
 * a partir de `sales`), e o rastreio de ciclo vive em `integration_import_runs`.
 * Nenhum código referencia a tabela — o model `App\Models\IntegrationSyncDay` foi
 * deletado nesta mesma release.
 *
 * Conferido em produção antes do drop (23/07/2026): 156 linhas, sem escrita desde
 * 11/05/2026. As linhas foram exportadas para
 * `storage/app/private/integration_sync_days_backup_2026-07-23.json` (volume
 * `prod_storage_data`, sobrevive a recriação de container).
 *
 * O `down()` recria o schema original — mas NÃO os dados; para restaurar, reimportar
 * o JSON do backup.
 */
return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        Schema::connection($this->connection)->dropIfExists('integration_sync_days');
    }

    public function down(): void
    {
        Schema::connection($this->connection)->create('integration_sync_days', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_integration_id')->constrained('tenant_integrations')->cascadeOnDelete();
            $table->string('resource', 32);
            $table->date('reference_date');
            $table->string('status', 24)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_integration_id', 'resource', 'reference_date'],
                'int_sync_days_tenant_resource_date_uq'
            );
            $table->index(
                ['resource', 'reference_date', 'status'],
                'int_sync_days_resource_date_status_idx'
            );
        });
    }
};
