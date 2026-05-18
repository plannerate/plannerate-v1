<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlanogramSubtemplate extends Model
{
    use BelongsToTenant, HasUlids, SoftDeletes, UsesTenantConnection;

    protected $fillable = [
        'tenant_id',
        'template_id',
        'code',
        'num_modules',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'num_modules' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PlanogramTemplate::class, 'template_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(PlanogramTemplateSlot::class, 'subtemplate_id')
            ->orderBy('module_number')
            ->orderBy('shelf_order')
            ->orderBy('ordering');
    }
}
