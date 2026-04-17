<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Models;

use App\Models\Traits\HasCategory;
use Callcocam\LaravelRaptor\Models\AbstractModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Product extends AbstractModel
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasCategory, HasFactory, SoftDeletes;

    protected $appends = ['mercadologico_cascading', 'image_url', 'hierarchy_path', 'dimensions_label', 'formatted_height', 'formatted_width', 'formatted_depth', 'formatted_weight'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        static::$landlord->enable();
    }

    protected function casts(): array
    {
        return [
            'sync_at' => 'datetime',
            'has_dimensions' => 'boolean',
        ];
    }

    /**
     * Label para exibição: "Com dimensão" quando width, height e depth são válidos; "Sem dimensão" caso contrário.
     */
    public function getDimensionsLabelAttribute(): string
    {
        return $this->has_dimensions ? 'Com dimensão' : 'Sem dimensão';
    }

    public function getImageUrlAttribute()
    {

        if (! $this->url) {
            $url = asset('/img/fallback/fall4.jpg');

            return $url;
        }

        // Se já é uma URL completa, retorna
        if (str_starts_with($this->url, 'http')) {
            return $this->url;
        }

        // Retorna a URL completa do storage
        return Storage::disk('public')->url($this->url);
    }

    /**
     * Retorna altura (ou valor aleatório entre 5 e 10 se não definido)
     */
    // public function getHeightAttribute($value)
    // {
    //     return $value;
    // }

    /**
     * Retorna largura (ou valor aleatório entre 5 e 10 se não definido)
     */
    // public function getWidthAttribute($value)
    // {
    //     return $value;
    // }

    /**
     * Retorna profundidade (ou valor aleatório entre 5 e 10 se não definido)
     */
    // public function getDepthAttribute($value)
    // {
    //     return $value;
    // }

    /**
     * Retorna altura formatada
     */
    public function getFormattedHeightAttribute()
    {
        return number_format($this->height, 2, ',', '.');
    }

    /**
     * Retorna largura formatada
     */
    public function getFormattedWidthAttribute()
    {
        return number_format($this->width, 2, ',', '.');
    }

    /**
     * Retorna profundidade formatada
     */
    public function getFormattedDepthAttribute()
    {
        return number_format($this->depth, 2, ',', '.');
    }

    /**
     * Retorna peso formatado
     */
    public function getFormattedWeightAttribute()
    {
        return $this->weight ? number_format($this->weight, 2, ',', '.') : null;
    }

    /**
     * Relacionamento com Client (apenas para referência no banco do landlord)
     *
     * NOTA: Quando usando conexão 'tenant' (banco do client), os produtos já têm client_id diretamente.
     * Cross-database relationship - use custom method instead of Eloquent relation
     * Para filtrar produtos por client_id, use o campo direto: $query->where('client_id', $clientId)
     */
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

    public function stores()
    {
        return $this->belongsToMany(Store::class, 'product_store', 'product_id', 'store_id');
    }

    public function getClientCascadingAttribute()
    {
        return [
            'client_id' => $this->client_id,
            'stores' => $this->stores,
        ];
    }

    // Relação com Dimension removida - colunas migradas para products
    // Use os campos diretos: width, height, depth, weight, unit, has_dimensions (Com dimensão / Sem dimensão)

    // protected function applyDomainContext(Builder $query): Builder
    // {
    //     if ($clientId = config('app.current_client_id')) {
    //         // Filtra diretamente por client_id (campo direto na tabela products)
    //         // Cada client tem seu próprio banco, então todos os produtos já pertencem ao client
    //         return $query->where('client_id', $clientId);
    //     }

    //     return $query;
    // }
}
