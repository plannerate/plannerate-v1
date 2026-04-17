<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Models;

use Callcocam\LaravelRaptor\Models\AbstractModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Sale extends AbstractModel
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'client_id',
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

        static::$landlord->enable();
    }

    protected function casts(): array
    {
        return [
            'acquisition_cost' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'total_profit_margin' => 'decimal:2',
            'sale_date' => 'date:Y-m-d',
            'total_sale_quantity' => 'decimal:3', // Alterado de integer para decimal:3 para suportar vendas por peso
            'total_sale_value' => 'decimal:2',
            'margem_contribuicao' => 'decimal:2',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Cross-database relationships - use custom methods instead of Eloquent relations

    public function getClientAttribute()
    {
        if (! $this->client_id) {
            return null;
        }

        return cache()->remember("client:{$this->client_id}", 3600, function () {
            return DB::connection(config('raptor.database.landlord_connection_name', 'landlord'))
                ->table('clients')
                ->where('id', $this->client_id)
                ->first();
        });
    }

    public function getStoreAttribute()
    {
        if (! $this->store_id) {
            return null;
        }

        return cache()->remember("store:{$this->store_id}", 3600, function () {
            return DB::connection(config('raptor.database.landlord_connection_name', 'landlord'))
                ->table('stores')
                ->where('id', $this->store_id)
                ->first();
        });
    }

    protected function slugTo()
    {
        return false;
    }

    protected function applyDomainContext(Builder $query): Builder
    {
        if ($clientId = config('app.current_client_id')) {
            return $query->where('client_id', $clientId);
        }

        return $query;
    }
}
