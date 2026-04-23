<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'is_active'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUlids, Notifiable, TwoFactorAuthenticatable;

    public function getConnectionName(): ?string
    {
        $containerKey = (string) config('multitenancy.current_tenant_container_key', 'currentTenant');

        if (app()->bound($containerKey) && app($containerKey) !== null) {
            return null;
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

                return null;
            }
        }

        return 'landlord';
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
        ];
    }

    /**
     * Get addresses associated to this user.
     */
    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }
}
