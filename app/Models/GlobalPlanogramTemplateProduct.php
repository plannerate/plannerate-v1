<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GlobalPlanogramTemplateProduct extends Model
{
    use HasUlids, SoftDeletes;

    protected $connection = 'landlord';

    protected $fillable = [
        'template_id',
        'ean',
        'description',
        'department',
        'category',
        'subcategory',
        'grouping',
        'grouping_normalized',
        'brand',
        'package_type',
        'package_content',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(GlobalPlanogramTemplate::class, 'template_id');
    }
}
