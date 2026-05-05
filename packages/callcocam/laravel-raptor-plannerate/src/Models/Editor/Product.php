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
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use BelongsToTenant, HasUlids, SoftDeletes, UsesPlannerateTenantConnection;

    protected $fillable = ['url'];

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
    public function getCategoryFullPathAttribute()
    {
        if ($cat = $this->category) {
            return $cat->full_path;
        }

        return null;
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

    public function getImageUrlAttribute()
    {
        if ($this->url) {
            return Storage::disk('public')->url($this->url);
        }

        $url = asset('/img/fallback/fall4.jpg');

        return $url;
    }
}
