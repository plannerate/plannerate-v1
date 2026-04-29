<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Models\Editor;

use App\Models\Traits\BelongsToTenant;
use Callcocam\LaravelRaptorPlannerate\Models\Traits\UsesPlannerateTenantConnection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use BelongsToTenant, HasUlids, SoftDeletes, UsesPlannerateTenantConnection;

    protected $fillable = [
        'tenant_id',
        'store_id',
        'product_id',
        'ean',
        'codigo_erp',
        'acquisition_cost',
        'sale_price',
        'total_profit_margin',
        'sale_date',
        'promotion',
        'total_sale_quantity',
        'total_sale_value',
        'margem_contribuicao',
        'extra_data',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return [
            'acquisition_cost' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'total_profit_margin' => 'decimal:2',
            'sale_date' => 'date',
            'total_sale_quantity' => 'decimal:3', // Alterado de integer para decimal:3 para suportar vendas por peso
            'total_sale_value' => 'decimal:2',
            'margem_contribuicao' => 'decimal:2',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
