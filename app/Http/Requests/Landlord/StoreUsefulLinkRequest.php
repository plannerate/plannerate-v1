<?php

namespace App\Http\Requests\Landlord;

use App\Models\UsefulLink;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreUsefulLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', UsefulLink::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
            'logo' => ['nullable', 'url', 'max:2048'],
            'description' => ['nullable', 'string'],
            'show_on_tenant_dashboard' => ['sometimes', 'boolean'],
        ];
    }
}
