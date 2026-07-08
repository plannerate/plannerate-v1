<?php

namespace App\Http\Requests\Landlord;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida a criação de uma categoria na árvore do mercadológico.
 *
 * Autorização é feita no controller; existência do pai é validada no serviço,
 * dentro do contexto do tenant.
 */
class StoreCategoryNodeRequest extends FormRequest
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
            'parent_id' => ['nullable', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'codigo' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['draft', 'published'])],
        ];
    }
}
