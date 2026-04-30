<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Http\Requests\Tenant\Plannerate\Editor;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request de validação para salvar mudanças no planograma
 *
 * Valida o payload de deltas enviado pelo frontend
 */
class SaveChangesRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Regras de validação para o payload
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantConnectionName = config('multitenancy.tenant_database_connection_name');
        $gondolasTable = is_string($tenantConnectionName) && $tenantConnectionName !== ''
            ? $tenantConnectionName.'.gondolas'
            : 'gondolas';

        return [
            'gondola_id' => ['required', 'string', Rule::exists($gondolasTable, 'id')],
            'changes' => 'required|array|min:1',
            'changes.*.type' => [
                'required',
                'string',
                'in:shelf_create,shelf_move,shelf_transfer,shelf_update,section_update,product_placement,layer_create,layer_update,segment_update,product_update,product_removal,gondola_update,gondola_scale,gondola_alignment,gondola_flow,segment_copy,segment_update,segment_transfer,segment_reorder',
            ],
            'changes.*.entityType' => 'required|string|in:shelf,section,product,layer,segment,gondola',
            'changes.*.entityId' => 'required|string',
            'changes.*.data' => 'required|array',
            'changes.*.timestamp' => 'required|integer',
            'metadata' => 'required|array',
            'metadata.total_changes' => 'required|integer',
            'metadata.last_modified' => 'required|integer',
        ];
    }
}
