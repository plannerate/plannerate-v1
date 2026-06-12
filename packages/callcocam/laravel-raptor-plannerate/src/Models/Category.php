<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Models;

use App\Models\Category as BaseCategory;
use Callcocam\LaravelRaptorPlannerate\Models\Traits\HasCategory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Categoria sob a ótica do editor de planogramas.
 *
 * Estende App\Models\Category (dono da entidade — fillable, casts, slug,
 * hierarquia parent/children/getFullHierarchy/getDescendantIds) e adiciona
 * apenas o que é específico do domínio planograma:
 *
 * - appends mercadologico_cascading/hierarchy_path (accessors cacheados do
 *   trait HasCategory, consumidos pelo payload do editor)
 * - relações products()/planograms() apontando para os models do pacote
 * - helpers de hierarquia usados pelo GondolaController e AbcAnalysisService
 *
 * As relações parent()/children() herdadas usam static::class, então pais e
 * filhos desta classe vêm como Category do pacote — os helpers recursivos
 * funcionam na cadeia inteira.
 */
class Category extends BaseCategory
{
    use HasCategory;

    protected $appends = ['mercadologico_cascading', 'hierarchy_path'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function planograms(): HasMany
    {
        return $this->hasMany(Planogram::class);
    }

    /**
     * Retorna todos os IDs da hierarquia (categoria atual + todas as categorias pai)
     */
    public function getHierarchyIds(): array
    {
        $ids = [];
        $current = $this;

        while ($current) {
            $ids[] = $current->id;
            $current = $current->parent;
        }

        return $ids;
    }

    /**
     * Retorna todas as categorias filhas recursivamente
     */
    public function getAllDescendants()
    {
        $descendants = collect();

        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getAllDescendants());
        }

        return $descendants;
    }

    /**
     * Retorna todos os IDs das categorias filhas (incluindo a atual)
     */
    public function getAllDescendantIds(): array
    {
        $ids = [$this->id];

        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->getAllDescendantIds());
        }

        return $ids;
    }
}
