<?php

namespace Callcocam\LaravelRaptorPlannerate\Models;

use App\Models\Gondola;
use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\UsesTenantConnection;
use App\Models\User;
use Callcocam\LaravelRaptorPlannerate\Enums\GenerationRunTrigger;
use Callcocam\LaravelRaptorPlannerate\Enums\ProposalStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Uma proposta de reotimização aguardando (ou tendo recebido) decisão do usuário.
 *
 * O layout proposto é guardado por inteiro: aprovar aplica o snapshot, não recalcula. Sem isso,
 * o usuário aprovaria um diff e receberia outro layout, porque as vendas mudam entre a análise e
 * a decisão.
 *
 * @property ProposalStatus $status
 */
class PlanogramReoptimizationProposal extends Model
{
    use BelongsToTenant, HasUlids, SoftDeletes, UsesTenantConnection;

    protected $table = 'planogram_reoptimization_proposals';

    /**
     * Os snapshots de layout somam ~150 KB por proposta. Ocultá-los por padrão evita que uma
     * listagem de propostas serialize megabytes de JSON para o Inertia sem ninguém perceber —
     * a tela do diff carrega o que precisa explicitamente com makeVisible().
     *
     * @var list<string>
     */
    protected $hidden = [
        'baseline_layout',
        'proposed_layout',
        'proposed_rejected',
    ];

    protected $fillable = [
        'tenant_id',
        'planogram_id',
        'gondola_id',
        'generation_run_id',
        'applied_run_id',
        'status',
        'trigger',
        'config_snapshot',
        'baseline_layout',
        'baseline_hash',
        'proposed_layout',
        'proposed_rejected',
        'diff_summary',
        'sales_period_start',
        'sales_period_end',
        'occupancy_before',
        'occupancy_after',
        'requested_by',
        'reviewed_by',
        'reviewed_at',
        'applied_at',
        'rejection_reason',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProposalStatus::class,
            'trigger' => GenerationRunTrigger::class,
            'config_snapshot' => 'array',
            'baseline_layout' => 'array',
            'proposed_layout' => 'array',
            'proposed_rejected' => 'array',
            'diff_summary' => 'array',
            'sales_period_start' => 'date',
            'sales_period_end' => 'date',
            'occupancy_before' => 'float',
            'occupancy_after' => 'float',
            'reviewed_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    /**
     * Só as colunas que as telas de listagem e o banner de pendência precisam — nunca os layouts.
     */
    public function scopeSummary(Builder $query): Builder
    {
        return $query->select([
            'id',
            'gondola_id',
            'planogram_id',
            'generation_run_id',
            'status',
            'trigger',
            'diff_summary',
            'sales_period_start',
            'sales_period_end',
            'occupancy_before',
            'occupancy_after',
            'reviewed_at',
            'applied_at',
            'created_at',
        ]);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ProposalStatus::Pending);
    }

    public function gondola(): BelongsTo
    {
        return $this->belongsTo(Gondola::class);
    }

    public function planogram(): BelongsTo
    {
        return $this->belongsTo(Planogram::class);
    }

    public function generationRun(): BelongsTo
    {
        return $this->belongsTo(PlanogramGenerationRun::class, 'generation_run_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
