<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class MercadologicoMoveRequest extends FormRequest
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
            'id' => ['required', 'string', 'exists:categories,id'],
            'category_id' => [
                'nullable',
                'string',
                'exists:categories,id',
                'different:id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'O ID da categoria é obrigatório.',
            'id.exists' => 'A categoria informada não existe.',
            'category_id.exists' => 'A categoria pai informada não existe.',
        ];
    }
}
