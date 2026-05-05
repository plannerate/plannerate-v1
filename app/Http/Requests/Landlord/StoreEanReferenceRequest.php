<?php

namespace App\Http\Requests\Landlord;

use App\Models\EanReference;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEanReferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', EanReference::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $width = (float) $this->input('width', 0);
        $height = (float) $this->input('height', 0);
        $depth = (float) $this->input('depth', 0);

        $this->merge([
            'ean' => EanReference::normalizeEan((string) $this->input('ean', '')),
            'has_dimensions' => $width > 0 && $height > 0 && $depth > 0,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ean' => ['required', 'string', 'max:32', Rule::unique('landlord.ean_references', 'ean')],
            'reference_description' => ['nullable', 'string'],
            'category_name' => ['nullable', 'string', 'max:255'],
            'category_slug' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'subbrand' => ['nullable', 'string', 'max:255'],
            'packaging_type' => ['nullable', 'string', 'max:255'],
            'packaging_size' => ['nullable', 'string', 'max:255'],
            'measurement_unit' => ['nullable', 'string', 'max:50'],
            'width' => ['nullable', 'numeric', 'min:0'],
            'height' => ['nullable', 'numeric', 'min:0'],
            'depth' => ['nullable', 'numeric', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:20'],
            'dimension_status' => ['nullable', 'in:draft,published'],
            'has_dimensions' => ['sometimes', 'boolean'],
            'image_front_url' => ['nullable', 'string', 'max:2048'],
            'image_side_url' => ['nullable', 'string', 'max:2048'],
            'image_top_url' => ['nullable', 'string', 'max:2048'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
