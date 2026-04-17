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

class Category extends AbstractModel
{
    /** @use HasFactory<\Database\Factories\CategoryFactory> */
    use HasCategory, HasFactory, SoftDeletes;

    protected $appends = ['mercadologico_cascading', 'hierarchy_path'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        static::$landlord->enable();
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Categorias filhas (sub-categorias)
     */
    public function children()
    {
        return $this->hasMany(Category::class, 'category_id');
    }

    /**
     * Retorna toda a hierarquia da categoria (do nível mais alto até esta categoria)
     */
    public function getFullHierarchy()
    {
        $hierarchy = collect();
        $current = $this;

        // Coleta a hierarquia de baixo para cima
        while ($current) {
            $hierarchy->prepend($current);
            $current = $current->parent;
        }

        return $hierarchy;
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

    /**
     * Retorna o caminho completo da hierarquia da categoria
     * Exemplo: "SUPERMERCADO > MERCEARIA TRADICIONAL > FARINÁCEOS > FARINHA > DE MILHO"
     * 
     * @return string
     */
    public function getFullPathAttribute(): string
    {
        return $this->getFullHierarchy()
            ->pluck('name')
            ->implode(' > ');
    }
}
