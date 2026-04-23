<?php

namespace App\Models;

use App\Models\Traits\HasCategory;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Tall\Sluggable\HasSlug;
use Tall\Sluggable\SlugOptions;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasCategory, HasFactory, HasUlids, SoftDeletes, HasSlug;

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
        'dimensions_ean',
        'width',
        'height',
        'depth',
        'weight',
        'unit',
        'dimensions_status',
        'dimensions_description',
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
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'depth' => 'decimal:2',
            'weight' => 'decimal:2',
            'sync_at' => 'datetime',
        ];
    }

    public function getImageUrlAttribute(): string
    {
        if (! $this->url) {
            return '';
        }

        if ($domain = app('currentTenant')->domains->first()) {
            return sprintf('%s://%s/storage/%s', request()->getScheme(), $domain->host, $this->url);
        }

        return Storage::disk('public')->url($this->url);
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
