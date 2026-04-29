<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Models\Editor;

use App\Models\Address;
use App\Models\Traits\BelongsToTenant;
use Callcocam\LaravelRaptorPlannerate\Models\Traits\UsesPlannerateTenantConnection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Store extends Model
{
    use BelongsToTenant, HasUlids, SoftDeletes, UsesPlannerateTenantConnection;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'document',
        'slug',
        'code',
        'external_id',
        'phone',
        'email',
        'status',
        'integrate_id',
        'description',
        'map_image_path',
        'map_regions',
    ];

    protected $with = ['address'];

    protected $appends = ['maps_integration'];

    protected function casts(): array
    {
        return [
            'map_regions' => 'array',
        ];
    }

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    public function address()
    {
        return $this->morphOne(Address::class, 'addressable');
    }

    public function getMapsIntegrationAttribute(): ?array
    {
        if (! $this->map_image_path) {
            return null;
        }

        return [
            'image_url' => Storage::disk('public')->url($this->map_image_path),
            'regions' => $this->map_regions ?? [],
        ];
    }
}
