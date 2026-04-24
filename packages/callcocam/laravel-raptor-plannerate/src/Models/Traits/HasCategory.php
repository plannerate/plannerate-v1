<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Models\Traits;

use Callcocam\LaravelRaptorPlannerate\Models\Editor\Category;
use Illuminate\Support\Str;

trait HasCategory
{
    /**
     * Hierarquia de níveis de categoria (ordem é importante)
     */
    protected const HIERARCHY_LEVELS = [
        1 => 'Segmento varejista',
        2 => 'Departamento',
        3 => 'Subdepartamento',
        4 => 'Categoria',
        5 => 'Subcategoria',
        6 => 'Segmento',
        7 => 'Subsegmento',
        8 => 'Atributo',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Retorna a hierarquia como string formatada
     */
    public function getHierarchyPathAttribute()
    {
        if (! $this->category_id) {
            return null;
        }

        return cache()->remember("hierarchy_path:{$this->category_id}", 7200, function () {
            if (! $this->category) {
                return null;
            }

            return $this->category->getFullHierarchy()
                ->pluck('name')
                ->implode(' > ');
        });
    }

    public function getMercadologicoCascadingAttribute()
    {
        if (! $this->category_id) {
            return [];
        }

        return cache()->remember("mercadologico_cascading:{$this->category_id}", 7200, function () {
            $mercadologico_nivel = [];

            if ($category = $this->category) {
                if ($categories = $category->getFullHierarchy()) {
                    foreach ($categories as $key => $level) {
                        if (! $level->level_name) {
                            $level->level_name = Str::slug(self::HIERARCHY_LEVELS[($key + 1)] ?? 'nivel_'.($key + 1), '_');
                            $level->nivel = ($key + 1);
                            $level->save();
                        }
                        $level_name = Str::slug($level->level_name, '_');
                        $mercadologico_nivel[$level_name] = $level->id;
                    }
                }
            }

            return $mercadologico_nivel;
        });
    }
}
