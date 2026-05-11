<?php

namespace App\Http\Requests\Landlord;

use App\Models\IntegrationApi;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportIntegrationApiConfigRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', IntegrationApi::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'spreadsheet' => ['required', 'file', 'mimes:json,txt', 'max:5120'],
            'truncate_before_import' => ['sometimes', 'boolean'],
        ];
    }
}
