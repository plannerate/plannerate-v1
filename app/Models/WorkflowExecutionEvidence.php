<?php

namespace App\Models;

use App\Enums\ExecutionEvidenceType;
use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Evidência (foto/arquivo) anexada a uma execução de gôndola na loja.
 */
class WorkflowExecutionEvidence extends Model
{
    use BelongsToTenant, HasUlids, SoftDeletes, UsesTenantConnection;

    /** "evidence" é incontável; fixa o nome real da tabela. */
    protected $table = 'workflow_execution_evidences';

    protected $fillable = [
        'user_id',
        'tenant_id',
        'workflow_gondola_execution_id',
        'type',
        'module_label',
        'product_id',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => ExecutionEvidenceType::class,
            'file_size' => 'integer',
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
