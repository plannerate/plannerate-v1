<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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
            'provisioned_at' => 'datetime',
        ];
    }

    /**
     * Resolve the effective user limit for this tenant's plan.
     * Priority:
     * 1) Active plan item with key "user_limit"
     * 2) plans.user_limit fallback
     */
    public function getPlanUserLimitAttribute(): ?int
    {
        $this->loadMissing('plan:id,user_limit');

        $plan = $this->plan;

        if (! $plan instanceof Plan) {
            return null;
        }

        $plan->loadMissing([
            'items' => static fn ($query) => $query
                ->where('key', 'user_limit')
                ->where('is_active', true),
        ]);

        $planItemLimit = $plan->items->firstWhere('key', 'user_limit')?->typedValue();

        if (is_int($planItemLimit)) {
            return $planItemLimit;
        }

        return $plan->user_limit;
    }

    /**
     * Get the plan used by the tenant.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get modules available for this tenant.
     */
    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'tenant_modules')
            ->withTimestamps();
    }

    public function scopeWhereHasActiveModule(Builder $query, string $slug): void
    {
        $query->whereHas('modules', function ($moduleQuery) use ($slug): void {
            $moduleQuery
                ->where('modules.slug', $slug)
                ->where('modules.is_active', true);
        });
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

    /**
     * Get addresses associated to this tenant.
     */
    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }
}
