<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlanogramTemplate extends Model
{
    use BelongsToTenant, HasUlids, SoftDeletes, UsesTenantConnection;

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'department',
        'category_id',
        'description',
        'is_active',
        'created_by',
        'origin',
        'source_gondola_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Templates visíveis nas listagens públicas: ativos e não-auto.
     * Templates auto (origin = 'auto', is_active = false) ficam ocultos até serem promovidos.
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('is_active', true)->where(function ($q): void {
            $q->whereNull('origin')->orWhere('origin', '!=', 'auto');
        });
    }

    /** Templates sintetizados pelo modo automático. */
    public function scopeAuto(Builder $query): void
    {
        $query->where('origin', 'auto');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subtemplates(): HasMany
    {
        return $this->hasMany(PlanogramSubtemplate::class, 'template_id');
    }
}
