<?php

namespace App\Http\Requests\Tenant;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Emissão de um link público de correção de dimensões.
 *
 * A listagem de dimensões manda a categoria filtrada; o editor manda a categoria do
 * planograma. Sem categoria, o link cobre o tenant inteiro.
 */
class StoreDimensionShareTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', new Product) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'string'],
        ];
    }
}
