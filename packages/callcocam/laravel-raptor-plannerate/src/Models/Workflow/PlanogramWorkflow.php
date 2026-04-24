<?php

namespace Callcocam\LaravelRaptorPlannerate\Models\Workflow;

use App\Models\Traits\BelongsToTenant;
use Callcocam\LaravelRaptorFlow\Contracts\Workable;
use Callcocam\LaravelRaptorFlow\Models\FlowConfigStep;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Planogram;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model de workflow que aponta para a tabela `planograms`.
 * Usado apenas no contexto do pacote laravel-raptor-flow (configurable em FlowConfigStep).
 * A tabela planograms fica no banco do tenant; use conexão default para o morph resolver no Kanban.
 */
class PlanogramWorkflow extends Model implements Workable
{
    use BelongsToTenant, HasUlids, SoftDeletes;

    protected $table = 'planograms';

    public function getConnectionName(): ?string
    {
        return null;
    }

    public function getWorkflowKey(): string
    {
        return (string) $this->getKey();
    }

    public function getWorkflowLabel(): string
    {
        return $this->name ?? (string) $this->getKey();
    }

    public function gondolas(): HasMany
    {
        return $this->hasMany(GondolaWorkflow::class, 'planogram_id');
    }

    public function configs()
    {
        return $this->hasMany(GondolaWorkflow::class, 'planogram_id');
    }

    /**
     * Retorna o model Editor\Planogram correspondente quando precisar da API completa.
     */
    public function toEditorPlanogram(): Planogram
    {
        return Planogram::findOrFail($this->getKey());
    }
}
