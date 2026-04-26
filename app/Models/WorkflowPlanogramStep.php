<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkflowPlanogramStep extends Model
{
    use BelongsToTenant, HasUlids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'planogram_id',
        'workflow_template_id',
        'name',
        'description',
        'estimated_duration_days',
        'role_id',
        'is_required',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'estimated_duration_days' => 'integer',
            'is_required' => 'boolean',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplate::class, 'workflow_template_id');
    }

    public function planogram(): BelongsTo
    {
        return $this->belongsTo(Planogram::class);
    }

    public function availableUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workflow_planogram_step_users', 'workflow_planogram_step_id')
            ->withTimestamps();
    }

    public function executions(): HasMany
    {
        return $this->hasMany(WorkflowGondolaExecution::class, 'workflow_planogram_step_id');
    }

    public function getNameAttribute(?string $value): string
    {
        return $value ?? $this->template?->name ?? '';
    }

    public function getDescriptionAttribute(?string $value): ?string
    {
        return $value ?? $this->template?->description;
    }

    public function getColorAttribute(?string $value): ?string
    {
        return $value ?? $this->template?->color;
    }

    public function getIconAttribute(?string $value): ?string
    {
        return $value ?? $this->template?->icon;
    }

    public function getEstimatedDurationDaysAttribute(?int $value): ?int
    {
        return $value ?? $this->template?->estimated_duration_days;
    }

    public function getSuggestedOrderAttribute(): int
    {
        return $this->template?->suggested_order ?? 0;
    }
}
