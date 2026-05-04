<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Models\Editor;

use App\Models\Traits\BelongsToTenant;
use Callcocam\LaravelRaptorPlannerate\Models\Traits\UsesPlannerateTenantConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Route;

class Gondola extends Model
{
    use BelongsToTenant, HasFactory, HasUlids, SoftDeletes, UsesPlannerateTenantConnection;

    // Não em $appends para evitar execução automática em cada instância carregada
    // O accessor ainda funciona ao ser acessado explicitamente
    protected $fillable = [
        'tenant_id',
        'planogram_id',
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
        $subdomain = str(request()->getHost())->before('.')->toString();

        return route('tenant.planograms.gondolas.editor', ['planogram' => $this->planogram_id, 'record' => $this->id, 'subdomain' => $subdomain], false);
    }

    protected function applyDomainContext(Builder $query): Builder
    {
        // BelongsToTenant global scope handles tenant filtering
        return $query;
    }
}
