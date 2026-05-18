<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\UsesTenantConnection;
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
        'global_template_id',
        'code',
        'name',
        'department',
        'description',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function globalTemplate(): BelongsTo
    {
        return $this->belongsTo(GlobalPlanogramTemplate::class, 'global_template_id');
    }

    public function subtemplates(): HasMany
    {
        return $this->hasMany(PlanogramSubtemplate::class, 'template_id');
    }

    public function templateProducts(): HasMany
    {
        return $this->hasMany(PlanogramTemplateProduct::class, 'template_id');
    }
}
