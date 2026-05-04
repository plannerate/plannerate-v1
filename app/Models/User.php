<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Pivots\LandlordPivot;
use App\Models\Traits\UsesTenantConnection;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'is_active'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUlids, Notifiable, SoftDeletes, TwoFactorAuthenticatable, UsesTenantConnection;

    public function getConnectionName(): ?string
    {
        $containerKey = (string) config('multitenancy.current_tenant_container_key', 'currentTenant');

        if (app()->bound($containerKey) && app($containerKey) !== null) {
            return $this->resolveTenantConnectionName();
        }

        $host = strtolower((string) request()->getHost());
        $tenantModel = config('multitenancy.tenant_model');

        if (is_string($tenantModel) && $tenantModel !== '' && $host !== '') {
            /** @var class-string<Model> $tenantModel */
            $tenant = $tenantModel::query()
                ->whereHas('domains', function ($query) use ($host): void {
                    $query
                        ->where('host', $host)
                        ->where('type', 'subdomain')
                        ->where('is_active', true);
                })
                ->first();

            if ($tenant !== null && method_exists($tenant, 'makeCurrent')) {
                $tenant->makeCurrent();

                return $this->resolveTenantConnectionName();
            }
        }

        return 'landlord';
    }

    private function resolveTenantConnectionName(): ?string
    {
        return $this->getConnectionNameFromTenantConfig();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_active' => 'boolean',
            'deleted_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get addresses associated to this user.
     */
    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    /**
     * Override Spatie Permission roles() relationship to use landlord connection.
     *
     * A user belongs to many roles across the landlord database.
     * In multi-tenant setup, roles/permissions are stored centrally in landlord connection,
     * but users exist in tenant databases. This method ensures role relationships
     * use the correct database connection.
     */
    public function roles(): BelongsToMany
    {
        $relation = $this->morphToMany(
            config('permission.models.role'),
            'model',
            config('permission.table_names.model_has_roles'),
            config('permission.column_names.model_morph_key'),
            config('permission.column_names.role_pivot_key')
        )
            ->withPivot(config('permission.column_names.team_foreign_key'))
            ->using(LandlordPivot::class);

        if (config('permission.teams', false)) {
            $relation->wherePivot(config('permission.column_names.team_foreign_key'), getPermissionsTeamId());
        }

        return $relation;
    }

    /**
     * Override Spatie Permission permissions() relationship to use landlord connection.
     *
     * A user belongs to many permissions across the landlord database.
     * This ensures direct permission assignments use the correct database connection.
     */
    public function permissions(): BelongsToMany
    {
        $relation = $this->morphToMany(
            config('permission.models.permission'),
            'model',
            config('permission.table_names.model_has_permissions'),
            config('permission.column_names.model_morph_key'),
            config('permission.column_names.permission_pivot_key')
        )
            ->withPivot(config('permission.column_names.team_foreign_key'))
            ->using(LandlordPivot::class);

        if (config('permission.teams', false)) {
            $relation->wherePivot(config('permission.column_names.team_foreign_key'), getPermissionsTeamId());
        }

        return $relation;
    }
}
