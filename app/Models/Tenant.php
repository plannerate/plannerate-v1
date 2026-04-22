<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Multitenancy\Models\Tenant as ModelsTenant;

class Tenant extends ModelsTenant
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasUlids;

    /**
     * The database connection used by the model.
     *
     * @var string
     */
    protected $connection = 'landlord';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'database',
        'status',
        'plan_id',
        'user_limit',
        'provisioned_at',
        'provisioning_error',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_limit' => 'integer',
            'provisioned_at' => 'datetime',
        ];
    }

    /**
     * Get the plan used by the tenant.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get all domains of the tenant.
     */
    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    /**
     * Get the primary domain of the tenant.
     */
    public function primaryDomain(): HasOne
    {
        return $this->domain();
    }

    /**
     * Get the single domain of the tenant.
     */
    public function domain(): HasOne
    {
        return $this->hasOne(TenantDomain::class);
    }
}
