<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlanogramTemplateProduct extends Model
{
    use BelongsToTenant, HasUlids, SoftDeletes, UsesTenantConnection;

    protected $fillable = [
        'tenant_id',
        'template_id',
        'ean',
        'product_id',
        'description',
        'department',
        'category',
        'subcategory',
        'grouping',
        'grouping_normalized',
        'brand',
        'package_type',
        'package_content',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(PlanogramTemplate::class, 'template_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
