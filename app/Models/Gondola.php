<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\UsesTenantConnection;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola as EditorGondola;
use Database\Factories\GondolaFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Tall\Sluggable\HasSlug;
use Tall\Sluggable\SlugOptions;

class Gondola extends EditorGondola
{
    /** @use HasFactory<GondolaFactory> */
    use BelongsToTenant, HasFactory, HasSlug, HasUlids, SoftDeletes, UsesTenantConnection;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'planogram_id',
        'linked_map_gondola_id',
        'linked_map_gondola_category',
        'name',
        'slug',
        'num_modulos',
        'location',
        'side',
        'flow',
        'alignment',
        'scale_factor',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'num_modulos' => 'integer',
            'scale_factor' => 'float',
        ];
    }

    /**
     * Get planogram associated to this gondola.
     */
    public function planogram(): BelongsTo
    {
        return $this->belongsTo(Planogram::class);
    }

    /**
     * @return SlugOptions
     */
    public function getSlugOptions()
    {
        if (is_string($this->slugTo())) {
            return SlugOptions::create()
                ->generateSlugsFrom($this->slugFrom())
                ->saveSlugsTo($this->slugTo());
        }
    }
}
