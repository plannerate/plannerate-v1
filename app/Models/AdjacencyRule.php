<?php

namespace App\Models;

use App\Enums\AdjacencyRuleType;
use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdjacencyRule extends Model
{
    use BelongsToTenant, HasUlids, SoftDeletes, UsesTenantConnection;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'source_category_id',
        'target_category_id',
        'rule_type',
        'weight',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'rule_type' => AdjacencyRuleType::class,
            'weight' => 'float',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'source_category_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'target_category_id');
    }
}
