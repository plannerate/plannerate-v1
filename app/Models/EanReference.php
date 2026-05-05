<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

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

    private function logWriteContext(string $operation): void
    {
        $connectionName = $this->getConnectionName();
        $connection = $this->getConnection();

        $caller = collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20))
            ->first(function (array $frame): bool {
                $class = (string) ($frame['class'] ?? '');

                return $class !== '' && ! str_starts_with($class, 'Illuminate\\') && ! str_starts_with($class, self::class);
            });

        Log::warning('EanReference write trace', [
            'operation' => $operation,
            'ean' => $this->ean,
            'model_connection_name' => $connectionName,
            'resolved_connection_name' => $connection->getName(),
            'resolved_database' => $connection->getDatabaseName(),
            'table' => $this->getTable(),
            'caller_class' => $caller['class'] ?? null,
            'caller_function' => $caller['function'] ?? null,
            'route' => request()?->route()?->getName(),
        ]);
    }
}
