<?php

namespace Callcocam\LaravelRaptorPlannerate\Models;

use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScoringWeights extends Model
{
    use BelongsToTenant, HasUlids, SoftDeletes, UsesTenantConnection;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'w_giro',
        'w_margem',
        'w_estrategico',
        'w_doh',
        'w_crescimento',
        'sales_window_months',
        'block_hierarchy_level',
        'adjacency_hierarchy_level',
    ];

    protected function casts(): array
    {
        return [
            'w_giro' => 'float',
            'w_margem' => 'float',
            'w_estrategico' => 'float',
            'w_doh' => 'float',
            'w_crescimento' => 'float',
            'sales_window_months' => 'integer',
            'block_hierarchy_level' => 'integer',
            'adjacency_hierarchy_level' => 'integer',
        ];
    }
}
