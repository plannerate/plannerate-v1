<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Models\Editor;

use App\Models\Traits\BelongsToTenant;
use Callcocam\LaravelRaptorPlannerate\Models\Traits\HasCategory;
use Callcocam\LaravelRaptorPlannerate\Models\Traits\UsesPlannerateTenantConnection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Planogram extends Model
{
    use BelongsToTenant, HasCategory, HasFactory, HasUlids, SoftDeletes, UsesPlannerateTenantConnection;

    protected $appends = ['mercadologico_cascading', 'hierarchy_path', 'start_month', 'end_month'];

    public function gondolas()
    {
        return $this->hasMany(Gondola::class);
    }

    public function getClientCascadingAttribute()
    {
        return [
            'store_id' => $this->store_id,
            'cluster_id' => $this->cluster_id,
        ];
    }

    /**
     * Retorna o mês inicial formatado para exibição (ex: "Janeiro 2025")
     */
    public function getStartMonthAttribute()
    {
        if (! $this->start_date) {
            return null;
        }

        $date = is_string($this->start_date) ? Carbon::parse($this->start_date) : $this->start_date;
        $date->locale('pt_BR');

        return $date->translatedFormat('F de Y');
    }

    /**
     * Retorna o mês final formatado para exibição (ex: "Julho 2025")
     */
    public function getEndMonthAttribute()
    {
        if (! $this->end_date) {
            return null;
        }

        $date = is_string($this->end_date) ? Carbon::parse($this->end_date) : $this->end_date;
        $date->locale('pt_BR');

        return $date->translatedFormat('F de Y');
    }

    /**
     * Retorna o mês inicial no formato YYYY-MM para inputs type="month"
     *
     * @return string|null Ex: "2025-01" ou null se não houver data
     */
    public function getStartMonthInput(): ?string
    {
        if (! $this->start_date) {
            return null;
        }

        $date = is_string($this->start_date) ? Carbon::parse($this->start_date) : $this->start_date;

        return $date->format('Y-m');
    }

    /**
     * Retorna o mês final no formato YYYY-MM para inputs type="month"
     *
     * @return string|null Ex: "2025-07" ou null se não houver data
     */
    public function getEndMonthInput(): ?string
    {
        if (! $this->end_date) {
            return null;
        }

        $date = is_string($this->end_date) ? Carbon::parse($this->end_date) : $this->end_date;

        return $date->format('Y-m');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
