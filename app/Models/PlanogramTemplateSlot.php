<?php

namespace App\Models;

use App\Enums\BrandExposure;
use App\Enums\FlavorExposure;
use App\Enums\PriceOrder;
use App\Enums\SizeOrder;
use App\Enums\SpaceFallback;
use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlanogramTemplateSlot extends Model
{
    use BelongsToTenant, HasUlids, SoftDeletes, UsesTenantConnection;

    protected $fillable = [
        'tenant_id',
        'subtemplate_id',
        'module_number',
        'shelf_order',
        'category',
        'subcategory',
        'grouping',
        'grouping_normalized',
        'min_facings',
        'priority',
        'price_order',
        'size_order',
        'brand_exposure',
        'flavor_exposure',
        'space_fallback',
        'use_target_stock',
        'ordering',
    ];

    protected function casts(): array
    {
        return [
            'module_number' => 'integer',
            'shelf_order' => 'integer',
            'min_facings' => 'integer',
            'priority' => 'integer',
            'ordering' => 'integer',
            'use_target_stock' => 'boolean',
            'price_order' => PriceOrder::class,
            'size_order' => SizeOrder::class,
            'brand_exposure' => BrandExposure::class,
            'flavor_exposure' => FlavorExposure::class,
            'space_fallback' => SpaceFallback::class,
        ];
    }

    public function subtemplate(): BelongsTo
    {
        return $this->belongsTo(PlanogramSubtemplate::class, 'subtemplate_id');
    }
}
