<?php

namespace App\Models;

use App\Enums\ExecutionEvidenceType;
use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Regra de obrigatoriedade de evidências para a Execução em Loja.
 *
 * Pode ser global (category_id nulo) ou escopada por categoria. Quando ausente,
 * o serviço aplica o padrão: 1 foto geral + 1 por módulo.
 */
class WorkflowExecutionEvidenceRequirement extends Model
{
    use BelongsToTenant, HasUlids, SoftDeletes, UsesTenantConnection;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'category_id',
        'type',
        'min_count',
        'per_module',
    ];

    protected function casts(): array
    {
        return [
            'type' => ExecutionEvidenceType::class,
            'min_count' => 'integer',
            'per_module' => 'boolean',
        ];
    }
}
