<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida o reparent de uma categoria na árvore do mercadológico do tenant.
 *
 * A autorização é feita no controller e a existência do destino é validada no
 * serviço; regras `exists:` aqui rodariam antes da resolução completa do contexto.
 */
class MoveCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'target_category_id' => ['nullable', 'string'],
        ];
    }
}
