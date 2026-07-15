<?php

namespace App\Models;

use App\Support\Modules\ModuleSlug;
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
        'settings',
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
            'settings' => 'array',
        ];
    }

    /**
     * Resolve the effective "tenant-admin" user limit for this tenant's plan.
     *
     * Trata do perfil administrativo legado. Os demais perfis administrativos
     * usam {@see self::roleUserLimit()}.
     *
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
     * Resolve o limite de usuários de um perfil administrativo específico,
     * dentro do plano deste tenant.
     *
     * - "tenant-admin" mantém o comportamento legado ({@see self::getPlanUserLimitAttribute()}).
     * - Demais perfis administrativos usam o plan_item com key "user_limit:{system_name}".
     *
     * @return int|null O limite configurado, ou null quando ilimitado/não definido.
     */
    public function roleUserLimit(string $systemName): ?int
    {
        if ($systemName === 'tenant-admin') {
            return $this->plan_user_limit;
        }

        $this->loadMissing('plan:id,user_limit');

        $plan = $this->plan;

        if (! $plan instanceof Plan) {
            return null;
        }

        // Consulta direta na relação (não na coleção carregada) para evitar
        // contaminação com itens carregados por outros accessors do plano.
        $value = $plan->items()
            ->where('key', 'user_limit:'.$systemName)
            ->where('is_active', true)
            ->first()
            ?->typedValue();

        return is_int($value) ? $value : null;
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
        $variants = ModuleSlug::variants($slug);

        $query->whereHas('modules', function ($moduleQuery) use ($variants): void {
            $moduleQuery
                ->whereIn('modules.slug', $variants)
                ->where('modules.is_active', true);
        });
    }

    /**
     * Get the roles available to this tenant.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_tenant')
            ->withTimestamps();
    }

    /**
     * Get all domains of the tenant.
     */
    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    public function integration(): HasOne
    {
        return $this->hasOne(TenantIntegration::class);
    }

    public function socialiteProvider(): HasOne
    {
        return $this->hasOne(TenantSocialiteProvider::class);
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
