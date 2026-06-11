<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\HasCategory;
use App\Models\Traits\UsesTenantConnection;
use Callcocam\LaravelRaptorPlannerate\Enums\CategoryRole;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Tall\Sluggable\HasSlug;
use Tall\Sluggable\SlugOptions;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use BelongsToTenant, HasFactory, HasSlug, HasUlids, SoftDeletes, UsesTenantConnection;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'category_id',
        'name',
        'slug',
        'level_name',
        'codigo',
        'status',
        'description',
        'nivel',
        'hierarchy_position',
        'full_path',
        'hierarchy_path',
        'is_placeholder',
        'role',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'codigo' => 'integer',
            'hierarchy_position' => 'integer',
            'hierarchy_path' => 'array',
            'is_placeholder' => 'boolean',
            'role' => CategoryRole::class,
        ];
    }

    /**
     * Get parent category.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'category_id');
    }

    /**
     * Get children categories.
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'category_id');
    }

    /**
     * Get products associated to this category.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Cadeia da raiz até este nó (inclusive), na ordem nível 1 → folha.
     * Usado pelo mercadológico em cascata e pelo trait {@see HasCategory}.
     */
    public function getFullHierarchy(): Collection
    {
        $chain = collect();
        $current = $this;
        $guard = 32;

        while ($current instanceof self && $guard-- > 0) {
            $chain->prepend($current);
            if ($current->category_id === null) {
                break;
            }
            $current = $current->relationLoaded('parent')
                ? $current->parent
                : self::query()->whereKey($current->category_id)->first();
        }

        return $chain->values();
    }

    /**
     * Profundidade mercadológica (1 = raiz do tenant).
     */
    public function getMercadologicoDepth(): int
    {
        return $this->getFullHierarchy()->count();
    }

    /**
     * Retorna array com o próprio ID e todos os IDs descendentes (BFS).
     * Usado pelo motor de placement para matching hierárquico de slots.
     *
     * @return list<string>
     */
    public static function getDescendantIds(string $categoryId): array
    {
        $ids = [$categoryId];
        $queue = [$categoryId];

        while ($queue !== []) {
            $parentId = array_shift($queue);
            $childIds = static::withoutGlobalScope(TenantScope::class)
                ->where('category_id', $parentId)
                ->pluck('id')
                ->all();

            foreach ($childIds as $childId) {
                $ids[] = $childId;
                $queue[] = $childId;
            }
        }

        return $ids;
    }

    /**
     * @return SlugOptions
     */
    public function getSlugOptions()
    {
        if (is_string($this->slugTo())) {
            return SlugOptions::create()
                ->generateSlugsFrom($this->slugFrom())
                ->saveSlugsTo($this->slugTo());
        }
    }
}
