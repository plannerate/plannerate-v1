<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Models;

use App\Models\Traits\BelongsToTenant;
use Callcocam\LaravelRaptorPlannerate\Models\Traits\UsesPlannerateTenantConnection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Segment extends Model
{
    use BelongsToTenant, HasUlids, SoftDeletes, UsesPlannerateTenantConnection;

    public function layer()
    {
        return $this->hasOne(Layer::class);
    }

    /**
     * Todas as camadas do segmento.
     *
     * O editor trata um segmento como tendo UMA camada (layer()), mas o PlanogramWriter grava um
     * loop de camadas por segmento — o schema permite várias. Quem precisa ler o layout como ele
     * está de fato no banco (o snapshot da reotimização) usa esta relação, não a singular, que
     * silenciosamente descartaria as demais.
     */
    public function layers()
    {
        return $this->hasMany(Layer::class);
    }

    public function shelf()
    {
        return $this->belongsTo(Shelf::class);
    }
}
