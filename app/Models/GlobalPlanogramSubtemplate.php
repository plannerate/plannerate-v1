<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GlobalPlanogramSubtemplate extends Model
{
    use HasUlids, SoftDeletes;

    protected $connection = 'landlord';

    protected $fillable = [
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
        return $this->belongsTo(GlobalPlanogramTemplate::class, 'template_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(GlobalPlanogramTemplateSlot::class, 'subtemplate_id')
            ->orderBy('module_number')
            ->orderBy('shelf_order')
            ->orderBy('ordering');
    }
}
