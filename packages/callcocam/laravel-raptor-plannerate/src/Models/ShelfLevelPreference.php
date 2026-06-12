<?php

namespace Callcocam\LaravelRaptorPlannerate\Models;

use App\Models\Category;
use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\UsesTenantConnection;
use Callcocam\LaravelRaptorPlannerate\Enums\ShelfLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShelfLevelPreference extends Model
{
    use BelongsToTenant, HasFactory, HasUlids, SoftDeletes, UsesTenantConnection;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'category_id',
        'preferred_level',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'preferred_level' => ShelfLevel::class,
        ];
    }

    /**
     * Get the category associated with this preference.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Scope to get preferences for a specific tenant.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope to get default preference for tenant (no category).
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeDefault($query)
    {
        return $query->whereNull('category_id');
    }

    /**
     * Scope to get category-specific preferences.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeByCategory($query)
    {
        return $query->whereNotNull('category_id');
    }
}
