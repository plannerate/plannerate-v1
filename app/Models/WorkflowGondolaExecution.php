<?php

namespace App\Models;

use App\Enums\WorkflowExecutionStatus;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkflowGondolaExecution extends Model
{
    use BelongsToTenant, HasUlids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'gondola_id',
        'workflow_planogram_step_id',
        'status',
        'current_responsible_id',
        'execution_started_by',
        'started_at',
        'completed_at',
        'sla_date',
        'paused_at',
        'notes',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'status' => WorkflowExecutionStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'sla_date' => 'datetime',
            'paused_at' => 'datetime',
            'context' => 'array',
        ];
    }

    public function gondola(): BelongsTo
    {
        return $this->belongsTo(Gondola::class);
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(WorkflowPlanogramStep::class, 'workflow_planogram_step_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(WorkflowHistory::class, 'workflow_gondola_execution_id')
            ->orderByDesc('performed_at');
    }

    public function currentResponsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_responsible_id');
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'execution_started_by');
    }

    public function scopeForGondola(Builder $query, string $gondolaId): Builder
    {
        return $query->where('gondola_id', $gondolaId);
    }

    public function scopeAtStep(Builder $query, string $stepId): Builder
    {
        return $query->where('workflow_planogram_step_id', $stepId);
    }
}
