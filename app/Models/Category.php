<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasUlids, SoftDeletes;

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
}
