<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EanReference extends Model
{
    use HasUlids, SoftDeletes;

    protected $connection = 'landlord';
    /**
     * @var list<string>
     */
    protected $fillable = [
        'ean',
        'category_id',
        'category_name',
        'category_slug',
        'reference_description',
        'brand',
        'subbrand',
        'packaging_type',
        'packaging_size',
        'measurement_unit',
        'width',
        'height',
        'depth',
        'weight',
        'unit',
        'has_dimensions',
        'dimension_status',
        'image_front_url',
        'image_side_url',
        'image_top_url',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'depth' => 'decimal:2',
            'weight' => 'decimal:2',
            'has_dimensions' => 'boolean',
            'metadata' => 'array',
        ];
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
