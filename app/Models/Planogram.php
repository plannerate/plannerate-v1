<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\UsesTenantConnection;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Planogram as EditorPlanogram;
use Database\Factories\PlanogramFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Tall\Sluggable\HasSlug;
use Tall\Sluggable\SlugOptions;

class Planogram extends EditorPlanogram
{
    /** @use HasFactory<PlanogramFactory> */
    use BelongsToTenant, HasFactory, HasSlug, HasUlids, SoftDeletes, UsesTenantConnection;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'template_id',
        'user_id',
        'store_id',
        'cluster_id',
        'name',
        'slug',
        'type',
        'category_id',
        'start_date',
        'end_date',
        'order',
        'qr_code_token',
        'qr_code_generated_at',
        'description',
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
            'start_date' => 'date',
            'end_date' => 'date',
            'order' => 'integer',
            'qr_code_generated_at' => 'datetime',
        ];
    }

    /**
     * Get store associated to this planogram.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get cluster associated to this planogram.
     */
    public function cluster(): BelongsTo
    {
        return $this->belongsTo(Cluster::class);
    }

    /**
     * Get category associated to this planogram.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function workflowSteps(): HasMany
    {
        return $this->hasMany(WorkflowPlanogramStep::class);
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
