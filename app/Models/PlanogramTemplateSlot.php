<?php

namespace App\Models;

use App\Enums\BrandExposure;
use App\Enums\CategoryRole;
use App\Enums\FacingExpansion;
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
        'category_id',
        'module_number',
        'shelf_order',
        'min_facings',
        'priority',
        'price_order',
        'size_order',
        'brand_exposure',
        'flavor_exposure',
        'space_fallback',
        'use_target_stock',
        'facing_expansion',
        'max_facings',
        'ordering',
        'role_override',
        'visual_criteria',
        'max_share_per_sku',
        'max_share_per_brand',
        'max_share_per_subcategory',
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
            'max_facings' => 'integer',
            'facing_expansion' => FacingExpansion::class,
            'price_order' => PriceOrder::class,
            'size_order' => SizeOrder::class,
            'brand_exposure' => BrandExposure::class,
            'flavor_exposure' => FlavorExposure::class,
            'space_fallback' => SpaceFallback::class,
            'role_override' => CategoryRole::class,
            'visual_criteria' => 'array',
            'max_share_per_sku' => 'integer',
            'max_share_per_brand' => 'integer',
            'max_share_per_subcategory' => 'integer',
        ];
    }

    /**
     * Papel efetivo: override do slot ou papel da categoria vinculada.
     * Retorna null se nenhum dos dois estiver configurado.
     */
    public function effectiveRole(): ?CategoryRole
    {
        return $this->role_override
            ?? ($this->relationLoaded('category') ? $this->category?->role : null);
    }

    public function subtemplate(): BelongsTo
    {
        return $this->belongsTo(PlanogramSubtemplate::class, 'subtemplate_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
