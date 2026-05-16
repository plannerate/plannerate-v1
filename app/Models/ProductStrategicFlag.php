<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductStrategicFlag extends Model
{
    use BelongsToTenant, HasUlids, SoftDeletes, UsesTenantConnection;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'product_id',
        'is_strategic',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'is_strategic' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
