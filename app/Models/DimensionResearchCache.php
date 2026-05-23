<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * Cache central de dimensões por EAN, armazenado por tenant.
 */
class DimensionResearchCache extends Model
{
    use UsesTenantConnection;

    protected $table = 'dimension_research_cache';

    protected $fillable = [
        'ean',
        'dimensions',
        'source',
        'confidence',
        'raw_response',
        'cached_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'dimensions' => 'array',
            'cached_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public static function findValidByEan(string $ean): ?self
    {
        return static::query()
            ->where('ean', $ean)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();
    }
}
