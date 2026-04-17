<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class MercadologicoStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'string', 'exists:categories,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome da categoria é obrigatório.',
            'name.max' => 'O nome não pode ter mais de 255 caracteres.',
            'category_id.exists' => 'A categoria pai informada não existe.',
        ];
    }
}
