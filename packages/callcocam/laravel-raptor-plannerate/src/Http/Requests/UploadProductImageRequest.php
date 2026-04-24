<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UploadProductImageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'max:5120'], // max 5MB
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required' => 'Por favor, selecione uma imagem.',
            'image.image' => 'O arquivo deve ser uma imagem válida.',
            'image.max' => 'A imagem deve ter no máximo 5MB.',
        ];
    }
}
