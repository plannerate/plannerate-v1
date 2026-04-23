<?php

namespace App\Models;

use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Tall\Sluggable\HasSlug;
use Tall\Sluggable\SlugOptions;

class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory, HasSlug, HasUlids, SoftDeletes;

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
    ];

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
