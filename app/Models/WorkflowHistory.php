<?php

namespace App\Models;

use App\Enums\WorkflowHistoryAction;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkflowHistory extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'workflow_gondola_execution_id',
        'action',
        'from_step_id',
        'to_step_id',
        'previous_responsible_id',
        'new_responsible_id',
        'description',
        'snapshot',
        'can_restore',
        'performed_at',
    ];

    protected function casts(): array
    {
        return [
            'action' => WorkflowHistoryAction::class,
            'snapshot' => 'array',
            'can_restore' => 'boolean',
            'performed_at' => 'datetime',
        ];
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(WorkflowGondolaExecution::class, 'workflow_gondola_execution_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
