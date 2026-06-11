<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\UsesTenantConnection;
use Callcocam\LaravelRaptorPlannerate\Models\Gondola as EditorGondola;
use Callcocam\LaravelRaptorPlannerate\Models\GondolaAnalysis;
use Callcocam\LaravelRaptorPlannerate\Models\GondolaSlotOverride;
use Callcocam\LaravelRaptorPlannerate\Models\Layer;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramRejectedProduct;
use Callcocam\LaravelRaptorPlannerate\Models\Section;
use Callcocam\LaravelRaptorPlannerate\Models\Segment;
use Callcocam\LaravelRaptorPlannerate\Models\Shelf;
use Database\Factories\GondolaFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Tall\Sluggable\HasSlug;
use Tall\Sluggable\SlugOptions;

class Gondola extends EditorGondola
{
    /** @use HasFactory<GondolaFactory> */
    use BelongsToTenant, HasFactory, HasSlug, HasUlids, SoftDeletes, UsesTenantConnection;

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

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'planogram_id',
        'template_id',
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
     * Overrides locais de configuração de geração por categoria.
     */
    public function generationOverrides(): HasMany
    {
        return $this->hasMany(GondolaSlotOverride::class);
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
