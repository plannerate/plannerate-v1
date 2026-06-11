<?php

namespace Callcocam\LaravelRaptorPlannerate\Models;

use App\Models\Gondola;
use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\UsesTenantConnection;
use Callcocam\LaravelRaptorPlannerate\Enums\BrandExposure;
use Callcocam\LaravelRaptorPlannerate\Enums\CategoryRole;
use Callcocam\LaravelRaptorPlannerate\Enums\FacingExpansion;
use Callcocam\LaravelRaptorPlannerate\Enums\FlavorExposure;
use Callcocam\LaravelRaptorPlannerate\Enums\PriceOrder;
use Callcocam\LaravelRaptorPlannerate\Enums\SizeOrder;
use Callcocam\LaravelRaptorPlannerate\Enums\SpaceFallback;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Override local de configuração de geração por categoria por gôndola.
 * Campos null = usar default do template slot correspondente.
 */
class GondolaSlotOverride extends Model
{
    use BelongsToTenant, HasUlids, SoftDeletes, UsesTenantConnection;

    protected $table = 'planogram_gondola_slot_overrides';

    protected $fillable = [
        'tenant_id',
        'gondola_id',
        'category_id',
        'min_facings',
        'max_facings',
        'price_order',
        'size_order',
        'brand_exposure',
        'flavor_exposure',
        'space_fallback',
        'facing_expansion',
        'use_target_stock',
        'role_override',
        'max_share_per_sku',
        'max_share_per_brand',
        'max_share_per_subcategory',
    ];

    protected function casts(): array
    {
        return [
            'min_facings' => 'integer',
            'max_facings' => 'integer',
            'use_target_stock' => 'boolean',
            'facing_expansion' => FacingExpansion::class,
            'price_order' => PriceOrder::class,
            'size_order' => SizeOrder::class,
            'brand_exposure' => BrandExposure::class,
            'flavor_exposure' => FlavorExposure::class,
            'space_fallback' => SpaceFallback::class,
            'role_override' => CategoryRole::class,
            'max_share_per_sku' => 'integer',
            'max_share_per_brand' => 'integer',
            'max_share_per_subcategory' => 'integer',
        ];
    }

    public function gondola(): BelongsTo
    {
        return $this->belongsTo(Gondola::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
