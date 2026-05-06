<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantSocialiteProvider extends Model
{
    use HasUlids, SoftDeletes;

    protected $connection = 'landlord';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'provider',
        'label',
        'client_id',
        'client_secret',
        'azure_tenant',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'client_secret' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function displayLabel(): string
    {
        if ($this->label) {
            return $this->label;
        }

        return match ($this->provider) {
            'google' => 'Google',
            'azure' => 'Microsoft',
            default => ucfirst($this->provider),
        };
    }
}
