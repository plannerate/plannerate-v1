<?php

namespace App\Http\Requests\Landlord;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida o reparent de uma categoria na árvore do mercadológico.
 *
 * A autorização é feita no controller (`authorize('update', $tenant)`) e a
 * existência do destino é validada no serviço, já dentro do contexto do tenant —
 * regras `exists:` aqui rodariam na conexão errada (fora do tenant).
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
