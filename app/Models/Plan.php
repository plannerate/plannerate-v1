<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
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
        'description',
        'price_cents',
        'user_limit',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'user_limit' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get tenants that use this plan.
     */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    /**
     * Get the plan's feature/limit items ordered for display.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PlanItem::class)->orderBy('sort_order');
    }

    /**
     * Return the typed value of a plan item by key, or null if unlimited/not set.
     */
    public function getLimit(string $key): int|bool|string|null
    {
        return $this->items->firstWhere('key', $key)?->typedValue();
    }
}
