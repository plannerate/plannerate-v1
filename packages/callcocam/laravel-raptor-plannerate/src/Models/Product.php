<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Models;

use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\HasStoreScopedMetrics;
use Callcocam\LaravelRaptorPlannerate\Models\Traits\UsesPlannerateTenantConnection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use BelongsToTenant, HasStoreScopedMetrics, HasUlids, SoftDeletes, UsesPlannerateTenantConnection;

    protected $guarded = ['id'];

    protected $appends = ['image_url', 'formatted_height', 'formatted_width', 'formatted_depth', 'category_full_path'];

    // Relação com Dimension removida - colunas migradas para products
    // Use os campos diretos: width, height, depth, weight, unit, dimension_status

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    // category_full_path é um campo calculado que retorna o caminho completo da categoria (ex: "Bebidas > Refrigerantes > Coca-Cola")
    // Calculado em tempo real via getFullHierarchy() em vez de confiar na coluna
    // categories.full_path, que é denormalizada e pode ficar desatualizada quando
    // a categoria é criada/editada fora da árvore de arrasto ou da importação
    // (ex: form padrão de categoria, que aceita full_path como texto livre).
    public function getCategoryFullPathAttribute()
    {
        if (! ($cat = $this->category)) {
            return null;
        }

        return cache()->remember("category_full_path:{$cat->id}", 7200, function () use ($cat) {
            return $cat->getFullHierarchy()->pluck('name')->implode(' > ');
        });
    }

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
     * URL pública da imagem com FALLBACK: o canvas do editor sempre precisa
     * renderizar alguma arte, então produto sem imagem recebe fall4.jpg.
     *
     * ATENÇÃO — duplicação deliberada com comportamento diferente: o
     * App\Models\Product tem o mesmo accessor mas devolve string vazia (páginas
     * do app tratam ausência de imagem na UI). Se alterar um, avalie o outro.
     */
    public function getImageUrlAttribute()
    {
        if ($this->url) {
            return Storage::disk('public')->url($this->url);
        }

        $url = asset('/img/fallback/fall4.jpg');

        return $url;
    }
}
