<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Tall\Sluggable\HasSlug;
use Tall\Sluggable\SlugOptions;

class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use BelongsToTenant, HasFactory, HasSlug, HasUlids, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'document',
        'slug',
        'code',
        'phone',
        'email',
        'status',
        'description',
        'map_image_path',
        'map_regions',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'map_regions' => 'array',
        ];
    }

    /**
     * Get clusters associated to this store.
     */
    public function clusters(): HasMany
    {
        return $this->hasMany(Cluster::class);
    }

    /**
     * Get addresses associated to this store.
     */
    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_store')
            ->withPivot(['tenant_id', 'last_synced_at'])
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
