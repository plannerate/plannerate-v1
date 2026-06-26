<?php

namespace App\Models;

use App\Enums\WorkflowExecutionStatus;
use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\UsesTenantConnection;
use App\Support\Authorization\PermissionName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkflowGondolaExecution extends Model
{
    use BelongsToTenant, HasUlids, SoftDeletes, UsesTenantConnection;

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
        return $this->belongsTo(Gondola::class)->withTrashed();
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

    public function evidences(): HasMany
    {
        return $this->hasMany(WorkflowExecutionEvidence::class, 'workflow_gondola_execution_id')
            ->latest();
    }

    public function divergences(): HasMany
    {
        return $this->hasMany(WorkflowExecutionDivergence::class, 'workflow_gondola_execution_id')
            ->latest();
    }

    public function currentResponsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_responsible_id');
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'execution_started_by');
    }

    public function scopeForResponsible(Builder $query, string $responsibleId): Builder
    {
        return $query->where('current_responsible_id', $responsibleId);
    }

    /**
     * Indica se a etapa atual da execução permite abrir o editor para edição.
     * Ponto único de decisão (access_mode) reutilizado pelo board, pelo mapa e
     * pela entrada do editor. Etapa não resolvida é tratada como editável (legado).
     */
    public function allowsEditing(): bool
    {
        $step = $this->relationLoaded('step') ? $this->step : $this->step()->first();

        return $step?->access_mode?->allowsEditing() ?? true;
    }

    /**
     * Decide se o link "Abrir editor" deve ser oferecido a este usuário.
     *
     * Combina, num único ponto reutilizado pelo board e pelo mapa de lojas:
     * permissão de editar gôndola + execução ativa + iniciada pelo próprio
     * usuário + etapa atual em modo de edição (access_mode). Caso contrário,
     * apenas a visualização em PDF deve ser oferecida.
     */
    public function canOpenEditorBy(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->can(PermissionName::TENANT_GONDOLAS_UPDATE)
            && $this->status === WorkflowExecutionStatus::Active
            && (string) $this->execution_started_by === (string) $user->id
            && $this->allowsEditing();
    }
}
