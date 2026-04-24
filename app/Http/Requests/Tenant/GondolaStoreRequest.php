<?php

namespace App\Http\Requests\Tenant;

use App\Models\Gondola;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GondolaStoreRequest extends FormRequest
{
    use InteractsWithTenantContext;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Gondola::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'planogram_id' => ['required', 'ulid', Rule::exists('planograms', 'id')->where('tenant_id', $tenantId)],
            'linked_map_gondola_id' => ['nullable', 'ulid'],
            'linked_map_gondola_category' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('gondolas', 'slug')->where('tenant_id', $tenantId)],
            'num_modulos' => ['required', 'integer', 'min:1'],
            'location' => ['nullable', 'string', 'max:255'],
            'side' => ['nullable', 'string', 'max:255'],
            'flow' => ['required', Rule::in(['left_to_right', 'right_to_left'])],
            'alignment' => ['required', Rule::in(['left', 'right', 'center', 'justify'])],
            'scale_factor' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['draft', 'published'])],
        ];
    }
}
