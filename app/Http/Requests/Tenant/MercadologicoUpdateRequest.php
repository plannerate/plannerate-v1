<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MercadologicoUpdateRequest extends FormRequest
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
        $id = $this->input('id');

        return [
            'id' => ['required', 'string', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('categories', 'slug')->ignore($id)->where('tenant_id', config('app.current_tenant_id')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id.required' => 'ID da categoria é obrigatório.',
            'id.exists' => 'Categoria não encontrada.',
            'name.required' => 'Informe o nome da categoria.',
            'name.max' => 'O nome não pode ter mais de 255 caracteres.',
            'slug.regex' => 'O slug deve conter apenas letras minúsculas, números e hífens.',
            'slug.unique' => 'Já existe outra categoria com esse slug.',
        ];
    }
}
