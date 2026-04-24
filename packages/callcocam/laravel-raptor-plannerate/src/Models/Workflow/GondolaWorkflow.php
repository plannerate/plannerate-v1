<?php

namespace Callcocam\LaravelRaptorPlannerate\Models\Workflow;

use App\Models\Traits\BelongsToTenant;
use Callcocam\LaravelRaptorFlow\Contracts\Workable;
use Callcocam\LaravelRaptorFlow\Models\FlowExecution;
use Callcocam\LaravelRaptorFlow\Traits\HasWorkflow;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Route;

/**
 * Model de workflow que aponta para a tabela `gondolas`.
 * Usado apenas no contexto do pacote laravel-raptor-flow (workable em FlowExecution).
 * Não substitui Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola; use este quando precisar de Workable/HasWorkflow.
 */
class GondolaWorkflow extends Model implements Workable
{
    use BelongsToTenant, HasUlids, HasWorkflow, SoftDeletes;

    protected $table = 'gondolas';

    protected $appends = ['route_gondolas'];

    public function getRouteGondolasAttribute(): ?string
    {
        if (! Route::has('tenant.plannerates.editor.gondolas.edit')) {
            return null;
        }

        return route('tenant.plannerates.editor.gondolas.edit', ['planogram' => $this->planogram_id, 'record' => $this->id]);
    }

    public function planogram(): BelongsTo
    {
        return $this->belongsTo(PlanogramWorkflow::class, 'planogram_id');
    }

    /**
     * Retorna o model Editor\Gondola correspondente quando precisar da API completa (sections, etc.).
     */
    public function toEditorGondola(): Gondola
    {
        return Gondola::findOrFail($this->getKey());
    }

    public function execution()
    {
        return $this->morphOne(FlowExecution::class, 'executionable', 'workable_type', 'workable_id');
    }
}
