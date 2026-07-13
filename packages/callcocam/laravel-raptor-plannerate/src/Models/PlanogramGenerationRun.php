<?php

namespace Callcocam\LaravelRaptorPlannerate\Models;

use App\Models\Gondola;
use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\UsesTenantConnection;
use Callcocam\LaravelRaptorPlannerate\Enums\GenerationRunStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Uma execução da geração automática/template de planograma.
 *
 * Persiste o que antes só existia como flash do Inertia (capacity_report,
 * validation_report) mais as métricas de precisão (ocupação, iterações). É o que
 * permite consultar depois "como ficou a geração de ontem" e comparar execuções.
 */
class PlanogramGenerationRun extends Model
{
    use BelongsToTenant, HasUlids, SoftDeletes, UsesTenantConnection;

    protected $table = 'planogram_generation_runs';

    protected $fillable = [
        'tenant_id',
        'planogram_id',
        'gondola_id',
        'user_id',
        'status',
        'mode',
        'config_snapshot',
        'template_id',
        'synth_template_id',
        'started_at',
        'finished_at',
        'duration_ms',
        'occupancy_avg',
        'occupancy_min',
        'occupancy_max',
        'iterations_run',
        'converged',
        'capacity_report',
        'validation_report',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'status' => GenerationRunStatus::class,
            'config_snapshot' => 'array',
            'capacity_report' => 'array',
            'validation_report' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_ms' => 'integer',
            'occupancy_avg' => 'float',
            'occupancy_min' => 'float',
            'occupancy_max' => 'float',
            'iterations_run' => 'integer',
            'converged' => 'boolean',
        ];
    }

    public function gondola(): BelongsTo
    {
        return $this->belongsTo(Gondola::class);
    }

    public function planogram(): BelongsTo
    {
        return $this->belongsTo(Planogram::class);
    }

    /**
     * Marca o início da execução (chamado pelo job ao pegar o worker).
     */
    public function markRunning(): void
    {
        $this->forceFill([
            'status' => GenerationRunStatus::Running,
            'started_at' => now(),
        ])->save();
    }

    /**
     * Marca a falha da execução, guardando a mensagem para exibição no histórico.
     */
    public function markFailed(string $message): void
    {
        $this->forceFill([
            'status' => GenerationRunStatus::Failed,
            'finished_at' => now(),
            'duration_ms' => $this->elapsedMs(),
            'error_message' => $message,
        ])->save();
    }

    /**
     * Milissegundos desde started_at (null quando a execução nem começou).
     */
    public function elapsedMs(): ?int
    {
        if ($this->started_at === null) {
            return null;
        }

        return (int) $this->started_at->diffInMilliseconds(now());
    }
}
