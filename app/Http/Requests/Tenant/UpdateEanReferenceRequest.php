<?php

namespace App\Http\Requests\Tenant;

use App\Models\EanReference;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEanReferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var EanReference|null $eanReference */
        $eanReference = $this->route('ean_reference');

        return $eanReference && ($this->user()?->can('update', $eanReference) ?? false);
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
        /** @var EanReference $eanReference */
        $eanReference = $this->route('ean_reference');

        return [
            'ean' => ['required', 'string', 'max:32', Rule::unique('ean_references', 'ean')->ignore($eanReference)],
            'reference_description' => ['nullable', 'string'],
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
        ];
    }
}
