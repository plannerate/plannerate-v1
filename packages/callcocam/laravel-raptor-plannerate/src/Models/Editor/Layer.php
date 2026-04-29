<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Models\Editor;

use App\Models\Traits\BelongsToTenant;
use Callcocam\LaravelRaptorPlannerate\Models\Traits\UsesPlannerateTenantConnection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Layer extends Model
{
    use BelongsToTenant, HasUlids, SoftDeletes, UsesPlannerateTenantConnection;

    protected static function boot(): void
    {
        parent::boot();

        // Auto-popula gondola_id a partir de segment→shelf→section para evitar JOINs na leitura
        static::creating(function (self $layer): void {
            if (! $layer->gondola_id && $layer->segment_id) {
                $layer->gondola_id = DB::table('segments')
                    ->join('shelves', 'shelves.id', '=', 'segments.shelf_id')
                    ->join('sections', 'sections.id', '=', 'shelves.section_id')
                    ->where('segments.id', $layer->segment_id)
                    ->value('sections.gondola_id');
            }
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
