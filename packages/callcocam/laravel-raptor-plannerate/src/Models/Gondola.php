<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Models;

use App\Models\Tenant;
use App\Models\Traits\BelongsToTenant;
use Callcocam\LaravelRaptorPlannerate\Enums\ReoptimizationFrequency;
use Callcocam\LaravelRaptorPlannerate\Models\Concerns\DeletesGondolaGraph;
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
    use BelongsToTenant, DeletesGondolaGraph, HasFactory, HasUlids, SoftDeletes, UsesPlannerateTenantConnection;

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
        'reoptimization_enabled',
        'reoptimization_frequency',
        'reoptimization_last_run_at',
        'reoptimization_next_run_at',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'reoptimization_enabled' => 'boolean',
            'reoptimization_frequency' => ReoptimizationFrequency::class,
            'reoptimization_last_run_at' => 'datetime',
            'reoptimization_next_run_at' => 'datetime',
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
