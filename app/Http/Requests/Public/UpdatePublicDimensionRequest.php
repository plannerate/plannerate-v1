<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação do preenchimento de dimensões via link público. A autorização é feita
 * pelo middleware do token (ValidateDimensionShareToken), portanto aqui apenas
 * validamos os três campos de dimensão.
 */
class UpdatePublicDimensionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'height' => ['required', 'numeric', 'gt:0'],
            'width' => ['required', 'numeric', 'gt:0'],
            'depth' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'height.required' => __('app.public_dimensions.validation.required'),
            'height.gt' => __('app.public_dimensions.validation.positive'),
            'width.required' => __('app.public_dimensions.validation.required'),
            'width.gt' => __('app.public_dimensions.validation.positive'),
            'depth.required' => __('app.public_dimensions.validation.required'),
            'depth.gt' => __('app.public_dimensions.validation.positive'),
        ];
    }
}
