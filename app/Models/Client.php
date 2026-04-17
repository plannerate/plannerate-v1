<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Models;

use Callcocam\LaravelRaptor\Models\AbstractModel;
use Callcocam\LaravelRaptor\Models\TenantDomain;
use Callcocam\LaravelRaptor\Support\Landlord\UsesLandlordConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends AbstractModel
{
    /** @use HasFactory<\Database\Factories\ClientFactory> */
    use HasFactory, SoftDeletes;
    use UsesLandlordConnection;
 

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        static::$landlord->enable();
    }

    protected $with = ['address', 'domain', 'client_integration'];

    public function address()
    {
        return $this->morphOne(Address::class, 'addressable');
    }

    public function client_integration()
    {
        return $this->hasOne(ClientIntegration::class);
    }

    /**
     * Domínio associado a este client
     */
    public function domain(): MorphOne
    {
        return $this->morphOne(TenantDomain::class, 'domainable');
    }

    /**
     * Retorna o domínio do client
     */
    public function getDomain(): ?TenantDomain
    {
        return $this->domain;
    }

    /**
     * Retorna o domínio como string
     */
    public function getDomainString(): ?string
    {
        return $this->domain?->domain;
    }

    /**
     * Produtos relacionados diretamente via client_id
     * Cada client tem seu próprio banco, então os produtos já têm client_id diretamente
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'client_id');
    }

    public function stores()
    {
        return $this->hasMany(Store::class);
    }

    public function storesDocument()
    {
        return $this->hasMany(Store::class)->whereNotNull('document');
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function integrationSyncLogs()
    {
        return $this->hasMany(IntegrationSyncLog::class);
    }

    protected function applyDomainContext(Builder $query): Builder
    {
        if ($clientId = config('app.current_client_id')) {
            return $query->where('id', $clientId);
        }

        return $query;
    }
}
