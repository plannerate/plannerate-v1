<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimilarGroup extends Model
{
    use BelongsToTenant, HasFactory, HasUlids, SoftDeletes, UsesTenantConnection;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'grouper_code',
        'name',
        'product_codes',
        'status',
        'description',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'product_codes' => 'array',
        ];
    }
}
