<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GlobalPlanogramTemplate extends Model
{
    use HasUlids, SoftDeletes;

    protected $connection = 'landlord';

    protected $fillable = [
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

    public function subtemplates(): HasMany
    {
        return $this->hasMany(GlobalPlanogramSubtemplate::class, 'template_id');
    }

    public function templateProducts(): HasMany
    {
        return $this->hasMany(GlobalPlanogramTemplateProduct::class, 'template_id');
    }

    public function shares(): HasMany
    {
        return $this->hasMany(TenantPlanogramTemplateShare::class, 'global_template_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
