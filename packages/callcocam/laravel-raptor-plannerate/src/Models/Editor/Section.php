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

class Section extends Model
{
    use BelongsToTenant, HasUlids, SoftDeletes, UsesPlannerateTenantConnection;

    protected $appends = ['section_width', 'section_height'];

    protected $hidden = ['gondola'];

    protected function casts(): array
    {
        return [
            'width' => 'float',
            'settings' => 'array',
            'height' => 'float',
            'base_width' => 'float',
            'base_height' => 'float',
            'base_depth' => 'float',
            'cremalheira_width' => 'float',
            'hole_height' => 'float',
            'hole_spacing' => 'float',
            'hole_width' => 'float',
        ];
    }

    public function shelves()
    {
        return $this->hasMany(Shelf::class)->orderBy('ordering');
    }

    public function gondola()
    {
        return $this->belongsTo(Gondola::class);
    }

    public function getSectionWidthAttribute()
    {
        $scaleFactor = 3;

        if ($this->relationLoaded('gondola') && $this->gondola) {
            $scaleFactor = (float) $this->gondola->scale_factor;
        }

        $totalWidth = ($this->width + 2) + $this->cremalheira_width * $scaleFactor;

        return $totalWidth;
    }

    public function getSectionHeightAttribute()
    {
        $scaleFactor = 3;

        if ($this->relationLoaded('gondola') && $this->gondola) {
            $scaleFactor = (float) $this->gondola->scale_factor;
        }

        return $this->height * $scaleFactor;
    }

    protected function slugTo()
    {
        return false;
    }
}
