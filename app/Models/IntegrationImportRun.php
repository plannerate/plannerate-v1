<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Registro de um ciclo de importação (discover → fetch → process).
 *
 * O discover grava o plano (startRun); o process acumula persisted_records
 * (recordPersisted, atômico); o sync:post-import reconcilia a cobertura depois
 * que as filas esvaziam. Ver a migration para o design.
 *
 * @property string $status running | complete | partial | failed
 */
class IntegrationImportRun extends Model
{
    use HasUlids;

    protected $connection = 'landlord';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        // reference_date fica como string 'Y-m-d' (sem cast date) para casar nas
        // queries: o cast date grava 'Y-m-d 00:00:00' e quebraria o match no SQLite.
        return [
            'expected_dates' => 'array',
            'force_full' => 'boolean',
            'expected_units' => 'integer',
            'covered_units' => 'integer',
            'persisted_records' => 'integer',
            'discovered_at' => 'datetime',
            'reconciled_at' => 'datetime',
        ];
    }

    /**
     * Abre (ou reabre) o run de um ciclo de discover. updateOrCreate na chave
     * lógica: um novo discover no mesmo dia reinicia o plano em vez de duplicar.
     *
     * @param  array{tenant_id: string, integration_id: string, path_key: string, store_id: ?string, mode: string, reference_date: string, expected_units: int, expected_dates?: array<int, string>|null, force_full?: bool}  $attributes
     */
    public static function startRun(array $attributes): self
    {
        return static::query()->updateOrCreate(
            [
                'integration_id' => $attributes['integration_id'],
                'path_key' => $attributes['path_key'],
                'store_id' => $attributes['store_id'] ?? null,
                'reference_date' => $attributes['reference_date'],
            ],
            [
                'tenant_id' => $attributes['tenant_id'],
                'mode' => $attributes['mode'],
                'expected_units' => $attributes['expected_units'],
                'expected_dates' => $attributes['expected_dates'] ?? null,
                'force_full' => $attributes['force_full'] ?? false,
                'persisted_records' => 0,
                'covered_units' => 0,
                'status' => 'running',
                'discovered_at' => now(),
                'reconciled_at' => null,
            ],
        );
    }

    /**
     * Acumula persisted_records de forma atômica (vários process concorrentes).
     * No-op silencioso se o run não existe (ex.: import legado sem run_id).
     */
    public static function recordPersisted(?string $runId, int $count): void
    {
        if ($runId === null || $count <= 0) {
            return;
        }

        static::query()->whereKey($runId)->update([
            'persisted_records' => DB::raw('persisted_records + '.$count),
            'updated_at' => now(),
        ]);
    }

    /**
     * Marca uma unidade (dia no daily / página no page) como FETCHADA — chamado
     * pelo FetchIntegrationPageJob quando o fetch conclui com sucesso, mesmo com
     * zero registros. É o sinal de cobertura correto: um dia sem venda (feriado)
     * teve o fetch executado → coberto, sem falso-positivo de parcial.
     * COALESCE: a coluna foi migrada como nullable; startRun inicia em 0.
     */
    public static function recordCovered(?string $runId): void
    {
        if ($runId === null) {
            return;
        }

        static::query()->whereKey($runId)->update([
            'covered_units' => DB::raw('COALESCE(covered_units, 0) + 1'),
            'updated_at' => now(),
        ]);
    }

    /** Runs de uma data ainda não reconciliados (ainda 'running'). */
    public function scopeRunningOn(Builder $query, string $referenceDate): Builder
    {
        return $query->where('reference_date', $referenceDate)->where('status', 'running');
    }
}
