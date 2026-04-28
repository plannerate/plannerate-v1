<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantIntegration extends Model
{
    use HasUlids, SoftDeletes;

    /**
     * @var string
     */
    protected $connection = 'landlord';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'integration_type',
        'identifier',
        'external_name',
        'external_name_ean',
        'external_name_status',
        'external_name_sale_date',
        'http_method',
        'api_url',
        'authentication_headers',
        'authentication_body',
        'config',
        'is_active',
        'last_sync',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'authentication_headers' => 'encrypted:array',
            'authentication_body' => 'encrypted:array',
            'config' => 'encrypted:array',
            'is_active' => 'boolean',
            'last_sync' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
