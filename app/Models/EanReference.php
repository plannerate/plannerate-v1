<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EanReference extends Model
{
    use BelongsToTenant, HasUlids, SoftDeletes, UsesTenantConnection;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'ean',
        'category_id',
        'reference_description',
        'brand',
        'subbrand',
        'packaging_type',
        'packaging_size',
        'measurement_unit',
    ];

    /**
     * @return BelongsTo<Category, EanReference>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @param  Builder<EanReference>  $query
     * @return Builder<EanReference>
     */
    public function scopeForNormalizedEan(Builder $query, string $ean): Builder
    {
        return $query->where('ean', self::normalizeEan($ean));
    }

    public static function normalizeEan(string $ean): string
    {
        $digitsOnly = preg_replace('/\D+/', '', $ean) ?? '';

        return $digitsOnly !== '' ? $digitsOnly : trim($ean);
    }
}
