<?php

namespace App\Models;

use App\Enums\PlacementFailureReason;
use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanogramRejectedProduct extends Model
{
    use BelongsToTenant, HasUlids, UsesTenantConnection;

    protected $fillable = [
        'tenant_id',
        'planogram_id',
        'gondola_id',
        'product_id',
        'product_name',
        'ean',
        'image_url',
        'product_width',
        'product_height',
        'rejection_reason',
        'slot_id',
        'category_name',
        'category_id',
        'module_number',
        'shelf_order',
    ];

    protected function casts(): array
    {
        return [
            'rejection_reason' => PlacementFailureReason::class,
            'product_width' => 'float',
            'product_height' => 'float',
            'module_number' => 'integer',
            'shelf_order' => 'integer',
        ];
    }

    public function gondola(): BelongsTo
    {
        return $this->belongsTo(Gondola::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function rejectionReasonLabel(): string
    {
        return $this->rejection_reason->label();
    }
}
