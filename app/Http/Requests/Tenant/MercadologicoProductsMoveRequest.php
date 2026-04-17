<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class MercadologicoProductsMoveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) config('app.current_client_id');
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_ids' => ['required', 'array'],
            'product_ids.*' => ['required', 'string', 'exists:products,id'],
            'category_id' => ['required', 'string', 'exists:categories,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_ids.required' => 'Selecione ao menos um produto.',
            'category_id.required' => 'A categoria de destino é obrigatória.',
            'category_id.exists' => 'A categoria de destino não existe.',
        ];
    }
}
