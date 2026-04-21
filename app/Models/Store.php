<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Models;

use Callcocam\LaravelRaptor\Models\AbstractModel;
use Callcocam\LaravelRaptor\Support\Landlord\UsesLandlordConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends AbstractModel
{
    use SoftDeletes;
    use UsesLandlordConnection;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'client_id',
        'database',
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
        'cluster_id',
    ];

    protected $with = ['address', 'client'];

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

        static::$landlord->enable();
    }

    public function address()
    {
        return $this->morphOne(Address::class, 'addressable');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Retorna os dados do mapa da loja para o componente Vue
     */
    public function getMapsIntegrationAttribute(): ?array
    {
        if (! $this->map_image_path) {
            return null;
        }

        return [
            'image_url' => $this->map_image_path
                ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->map_image_path)
                : null,
            'regions' => $this->map_regions ?? [],
        ];
    }

    protected function applyDomainContext(Builder $query): Builder
    {
        if ($clientId = config('app.current_client_id')) {

            return $query->where('client_id', $clientId);
        }

        return $query;
    }
}
