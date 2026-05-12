<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Tall\Sluggable\HasSlug;
use Tall\Sluggable\SlugOptions;

class IntegrationApi extends Model
{
    use HasUlids, SoftDeletes, HasSlug;

    /** @var string */
    protected $connection = 'landlord';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'requests',
        'response',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'requests' => 'array',
            'response' => 'array',
            'is_active' => 'boolean',
        ];
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
