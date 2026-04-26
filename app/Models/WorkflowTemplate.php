<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkflowTemplate extends Model
{
    use BelongsToTenant, HasUlids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'template_next_step_id',
        'template_previous_step_id',
        'name',
        'slug',
        'description',
        'suggested_order',
        'estimated_duration_days',
        'default_role_id',
        'color',
        'icon',
        'is_required_by_default',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'suggested_order' => 'integer',
            'estimated_duration_days' => 'integer',
            'is_required_by_default' => 'boolean',
        ];
    }

    public function nextStep(): BelongsTo
    {
        return $this->belongsTo(self::class, 'template_next_step_id');
    }

    public function previousStep(): BelongsTo
    {
        return $this->belongsTo(self::class, 'template_previous_step_id');
    }

    public function suggestedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workflow_template_users')
            ->withTimestamps();
    }

    public function configSteps(): HasMany
    {
        return $this->hasMany(WorkflowPlanogramStep::class);
    }
}
