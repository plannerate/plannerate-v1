<?php

namespace App\Http\Requests\Landlord;

use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantIntegrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Tenant|null $tenant */
        $tenant = $this->route('tenant');

        return $tenant && ($this->user()?->can('update', $tenant) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Tenant|null $tenant */
        $tenant = $this->route('tenant');
        $integrationExists = $tenant?->integration()->exists() ?? false;

        return [
            'integration_type' => ['required', 'string', Rule::in(['sysmo'])],
            'identifier' => ['required', 'string', 'max:255'],
            'external_name' => ['required', 'string', 'max:255'],
            'external_name_ean' => ['nullable', 'string', 'max:255'],
            'external_name_status' => ['nullable', 'string', 'max:255'],
            'external_name_sale_date' => ['nullable', 'string', 'max:255'],
            'http_method' => ['required', 'string', Rule::in(['GET', 'POST', 'PUT', 'PATCH'])],
            'api_url' => ['required', 'url', 'max:255'],
            'auth_username' => ['required', 'string', 'max:255'],
            'auth_password' => [
                Rule::requiredIf(! $integrationExists),
                'nullable',
                'string',
                'max:255',
            ],
            'partner_key' => ['required', 'string', 'max:255'],
            'empresa' => ['nullable', 'string', 'max:255'],
            'days_to_maintain' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'auto_processing_enabled' => ['sometimes', 'boolean'],
            'processing_time' => ['nullable', 'date_format:H:i'],
            'initial_setup_date' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function integrationPayload(): array
    {
        $validated = $this->validated();
        /** @var Tenant|null $tenant */
        $tenant = $this->route('tenant');
        $existingIntegration = $tenant?->integration;
        $existingHeaders = is_array($existingIntegration?->authentication_headers) ? $existingIntegration->authentication_headers : [];
        $resolvedPassword = $validated['auth_password'] ?? $existingHeaders['auth_password'] ?? '';

        return [
            'integration_type' => $validated['integration_type'],
            'identifier' => $validated['identifier'],
            'external_name' => $validated['external_name'],
            'external_name_ean' => $validated['external_name_ean'] ?? null,
            'external_name_status' => $validated['external_name_status'] ?? null,
            'external_name_sale_date' => $validated['external_name_sale_date'] ?? null,
            'http_method' => strtoupper((string) $validated['http_method']),
            'api_url' => $validated['api_url'],
            'authentication_headers' => [
                'auth_username' => $validated['auth_username'],
                'auth_password' => (string) $resolvedPassword,
            ],
            'authentication_body' => [
                'partner_key' => $validated['partner_key'],
                'empresa' => $validated['empresa'] ?? $validated['identifier'],
            ],
            'config' => [
                'days_to_maintain' => $validated['days_to_maintain'] ?? 120,
                'auto_processing_enabled' => (bool) ($validated['auto_processing_enabled'] ?? true),
                'processing_time' => $validated['processing_time'] ?? '02:00',
                'initial_setup_date' => $validated['initial_setup_date'] ?? null,
            ],
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ];
    }
}
