<?php

namespace App\Models;

use App\Enums\DimensionStatus;
use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\HasCategory;
use App\Models\Traits\UsesTenantConnection;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Tall\Sluggable\HasSlug;
use Tall\Sluggable\SlugOptions;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use BelongsToTenant, HasCategory, HasFactory, HasSlug, HasUlids, SoftDeletes, UsesTenantConnection;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'image_id',
        'category_id',
        'client_id',
        'name',
        'slug',
        'ean',
        'codigo_erp',
        'stackable',
        'perishable',
        'flammable',
        'hangable',
        'description',
        'sales_status',
        'sales_purchases',
        'status',
        'sync_source',
        'resolution_status',
        'resolution_details',
        'sync_at',
        'no_sales',
        'no_purchases',
        'url',
        'type',
        'reference',
        'fragrance',
        'flavor',
        'color',
        'brand',
        'subbrand',
        'packaging_type',
        'packaging_size',
        'measurement_unit',
        'packaging_content',
        'unit_measure',
        'auxiliary_description',
        'additional_information',
        'sortiment_attribute',
        'sortiment_attribute_levels',
        'dimensions_ean',
        'width',
        'height',
        'depth',
        'weight',
        'unit',
        'has_dimensions',
        'dimension_publish_status',
        'dimension_status',
        'similar_to_product_id',
        'dimension_source',
        'dimension_source_url',
        'dimension_confidence',
        'dimension_reasoning',
        'dimension_warnings',
        'dimension_researched_at',
        'dimension_approved_by',
        'dimension_approved_at',
        'net_content',
        'description_embedding',
        'current_stock',
        'price',
    ];

    protected $appends = [
        'image_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stackable' => 'boolean',
            'perishable' => 'boolean',
            'flammable' => 'boolean',
            'hangable' => 'boolean',
            'no_sales' => 'boolean',
            'no_purchases' => 'boolean',
            'has_dimensions' => 'boolean',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'depth' => 'decimal:2',
            'weight' => 'decimal:2',
            'net_content' => 'decimal:3',
            'sync_at' => 'datetime',
            'last_purchase_date' => 'date',
            'resolution_details' => 'array',
            'dimension_warnings' => 'array',
            'dimension_researched_at' => 'datetime',
            'dimension_approved_at' => 'datetime',
            'dimension_status' => DimensionStatus::class,
            'description_embedding' => 'array',
        ];
    }

    public function similarTo(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'similar_to_product_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('dimension_status', DimensionStatus::Pending);
    }

    public function scopeAwaitingApproval(Builder $query): Builder
    {
        return $query->where('dimension_status', DimensionStatus::AwaitingApproval);
    }

    public function scopeWithApprovedDimensions(Builder $query): Builder
    {
        return $query->where('dimension_status', DimensionStatus::Approved);
    }

    public function scopeNeedingResearch(Builder $query): Builder
    {
        return $query->whereIn('dimension_status', [
            DimensionStatus::Pending->value,
            DimensionStatus::Rejected->value,
            DimensionStatus::NotFound->value,
        ]);
    }

    public function getImageUrlAttribute(): string
    {
        if (! $this->url) {
            return '';
        }

        // if ($domain = app('currentTenant')->domains->first()) {
        //     return sprintf('%s://%s/storage/%s', request()->getScheme(), $domain->host, $this->url);
        // }

        return Storage::disk('public')->url($this->url);
    }

    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'product_store')
            ->withPivot(['tenant_id', 'last_synced_at'])
            ->withTimestamps();
    }

    public function similarGroups(): BelongsToMany
    {
        return $this->belongsToMany(SimilarGroup::class, 'product_similar_group')
            ->withPivot(['tenant_id'])
            ->withTimestamps();
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
