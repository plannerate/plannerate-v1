<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Models\Editor;

use App\Models\PlanogramRejectedProduct;
use App\Models\Tenant;
use App\Models\Traits\BelongsToTenant;
use App\Models\WorkflowGondolaExecution;
use App\Models\WorkflowHistory;
use Callcocam\LaravelRaptorPlannerate\Models\Traits\UsesPlannerateTenantConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

class Gondola extends Model
{
    use BelongsToTenant, HasFactory, HasUlids, SoftDeletes, UsesPlannerateTenantConnection;

    protected static function booted(): void
    {
        static::deleting(function (Gondola $gondola): void {
            $sectionIds = Section::where('gondola_id', $gondola->id)->pluck('id');
            $shelfIds = Shelf::whereIn('section_id', $sectionIds)->pluck('id');
            $segmentIds = Segment::whereIn('shelf_id', $shelfIds)->pluck('id');

            Layer::whereIn('segment_id', $segmentIds)->delete();
            Segment::whereIn('shelf_id', $shelfIds)->delete();
            Shelf::whereIn('section_id', $sectionIds)->delete();
            Section::where('gondola_id', $gondola->id)->delete();

            GondolaAnalysis::where('gondola_id', $gondola->id)->delete();

            $executionIds = WorkflowGondolaExecution::where('gondola_id', $gondola->id)->pluck('id');
            WorkflowHistory::whereIn('workflow_gondola_execution_id', $executionIds)->delete();
            WorkflowGondolaExecution::where('gondola_id', $gondola->id)->delete();

            PlanogramRejectedProduct::where('gondola_id', $gondola->id)->delete();
        });
    }

    // Não em $appends para evitar execução automática em cada instância carregada
    // O accessor ainda funciona ao ser acessado explicitamente
    protected $fillable = [
        'tenant_id',
        'planogram_id',
        'template_id',
        'generation_mode',
        'user_id',
        'name',
        'slug',
        'num_modulos',
        'location',
        'side',
        'flow',
        'alignment',
        'scale_factor',
        'linked_map_gondola_id',
        'linked_map_gondola_category',
        'status',
    ];

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

    public function getRouteGondolasAttribute()
    {
        if (! Route::has('tenant.planograms.gondolas.editor')) {
            return null;
        }
        $subdomain = Cache::rememberForever("tenants_{$this->tenant_id}_subdomain", function () {
            // Lógica para obter o subdomínio do tenant, por exemplo:
            if ($tenant = Tenant::current()) {
                return str($tenant->domain->host)->before('.')->toString() ?? null;
            }

            return null;
        });

        return route('tenant.planograms.gondolas.editor', ['planogram' => $this->planogram_id, 'record' => $this->id, 'subdomain' => $subdomain], false);
    }

    protected function applyDomainContext(Builder $query): Builder
    {
        // BelongsToTenant global scope handles tenant filtering
        return $query;
    }
}
