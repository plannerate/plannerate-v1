<?php

namespace App\Http\Requests\Landlord;

use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateTenantRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const AVAILABLE_STATUSES = ['provisioning', 'active', 'suspended', 'inactive'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name', ''));
        $rawSlug = trim((string) $this->input('slug', ''));
        $slug = Str::slug($rawSlug !== '' ? $rawSlug : $name);
        $database = trim((string) $this->input('database', ''));

        if ($database === '' && $slug !== '') {
            $database = sprintf('tenant_%s', str_replace('-', '_', $slug));
        }

        $this->merge([
            'slug' => $slug,
            'database' => Str::lower($database),
            'domain_is_active' => $this->boolean('domain_is_active', true),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Tenant $tenant */
        $tenant = $this->route('tenant');
        $primaryDomainId = $tenant->primaryDomain()->value('id');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('landlord.tenants', 'slug')->ignore($tenant)],
            'database' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_]+$/', Rule::unique('landlord.tenants', 'database')->ignore($tenant)],
            'status' => ['required', 'string', Rule::in(self::AVAILABLE_STATUSES)],
            'plan_id' => ['nullable', 'string', Rule::exists('landlord.plans', 'id')],
            'user_limit' => ['nullable', 'integer', 'min:1'],
            'host' => ['required', 'string', 'max:255', Rule::unique('landlord.tenant_domains', 'host')->ignore($primaryDomainId)],
            'domain_is_active' => ['sometimes', 'boolean'],
        ];
    }
}
