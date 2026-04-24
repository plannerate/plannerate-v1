<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Models\Editor;

use App\Models\Traits\BelongsToTenant;
use Callcocam\LaravelRaptorFlow\Models\FlowExecution;
use Callcocam\LaravelRaptorPlannerate\Support\WorkflowMorphMap;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Route;

class Gondola extends Model
{
    use BelongsToTenant, HasFactory, HasUlids, SoftDeletes;

    // Apenas route_gondolas (sem query); execution e workflow_execution_count sob demanda (evita loop/N+1)
    protected $appends = ['route_gondolas'];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function sections()
    {
        return $this->hasMany(Section::class, 'gondola_id')
            ->orderBy('ordering', 'asc');
    }

    public function planogram()
    {
        return $this->belongsTo(Planogram::class);
    }

    public function workflowExecution()
    {
        return $this->hasOne(FlowExecution::class, 'workable_id');
    }

    public function getExecutionAttribute()
    {
        $execution = FlowExecution::query()
            ->whereIn('workable_type', WorkflowMorphMap::gondolaWorkflowTypes())
            ->where('workable_id', $this->id)
            ->first();

        if (! $execution) {
            return null;
        }

        return (object) [
            'id' => $execution->id,
            'started_at' => $execution->started_at?->format('Y-m-d'),
            'status' => $execution->status?->value ?? $execution->status,
            'sla_date' => $execution->sla_date,
        ];
    }

    public function getWorkflowExecutionCountAttribute(): int
    {
        return FlowExecution::query()
            ->whereIn('workable_type', WorkflowMorphMap::gondolaWorkflowTypes())
            ->where('workable_id', $this->id)
            ->count();
    }

    public function getRouteGondolasAttribute()
    {
        if (! Route::has('tenant.plannerates.editor.gondolas.edit')) {
            return null;
        }

        return route('tenant.plannerates.editor.gondolas.edit', ['planogram' => $this->planogram_id, 'record' => $this->id]);
    }

    protected function applyDomainContext(Builder $query): Builder
    {
        // BelongsToTenant global scope handles tenant filtering
        return $query;
    }
}
