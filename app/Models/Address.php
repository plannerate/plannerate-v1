<?php

namespace App\Models;

use App\Models\Traits\UsesTenantConnection;
use Database\Factories\AddressFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Address extends Model
{
    /** @use HasFactory<AddressFactory> */
    use HasFactory, HasUlids, SoftDeletes, UsesTenantConnection;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'tenant_id',
        'user_id',
        'addressable_type',
        'addressable_id',
        'name',
        'zip_code',
        'street',
        'number',
        'complement',
        'reference',
        'additional_information',
        'district',
        'city',
        'country',
        'state',
        'is_default',
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
            'is_default' => 'boolean',
        ];
    }

    public function getConnectionName(): ?string
    {
        $containerKey = (string) config('multitenancy.current_tenant_container_key', 'currentTenant');

        if (app()->bound($containerKey) && app($containerKey) !== null) {
            return $this->getConnectionNameFromTenantConfig();
        }

        return 'landlord';
    }

    /**
     * Get the owning addressable model.
     */
    public function addressable(): MorphTo
    {
        return $this->morphTo();
    }
}
