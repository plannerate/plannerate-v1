<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida a edição de uma categoria da árvore do mercadológico do tenant.
 */
class UpdateCategoryNodeRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'codigo' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'importer'])],
        ];
    }
}
