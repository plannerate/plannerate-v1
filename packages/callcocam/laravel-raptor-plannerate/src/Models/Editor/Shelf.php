<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Models\Editor;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shelf extends Model
{
    use BelongsToTenant, HasUlids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'shelf_width' => 'integer',
            'shelf_height' => 'integer',
            'shelf_depth' => 'integer',
            'shelf_position' => 'integer',
        ];
    }

    public function segments()
    {
        return $this->hasMany(Segment::class)->orderBy('ordering', 'asc');
    }
}
