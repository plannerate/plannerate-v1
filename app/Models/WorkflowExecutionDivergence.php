<?php

namespace App\Models;

use App\Enums\ExecutionDivergenceStatus;
use App\Enums\ExecutionDivergenceType;
use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Divergência apontada durante a execução de uma gôndola na loja.
 */
class WorkflowExecutionDivergence extends Model
{
    use BelongsToTenant, HasUlids, SoftDeletes, UsesTenantConnection;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'workflow_gondola_execution_id',
        'type',
        'module_label',
        'shelf_label',
        'position_label',
        'product_id',
        'notes',
        'status',
        'resolution_notes',
        'photos',
    ];

    protected function casts(): array
    {
        return [
            'type' => ExecutionDivergenceType::class,
            'status' => ExecutionDivergenceStatus::class,
            'photos' => 'array',
        ];
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(WorkflowGondolaExecution::class, 'workflow_gondola_execution_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
