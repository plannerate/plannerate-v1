<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanItem extends Model
{
    use HasUlids;

    protected $connection = 'landlord';

    protected $fillable = [
        'plan_id',
        'key',
        'label',
        'value',
        'type',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** Cast value to the declared type. Returns null when value is null (= unlimited / disabled). */
    public function typedValue(): int|bool|string|null
    {
        if ($this->value === null) {
            return null;
        }

        return match ($this->type) {
            'integer' => (int) $this->value,
            'boolean' => (bool) $this->value,
            default => $this->value,
        };
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
