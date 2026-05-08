<?php

namespace App\Http\Requests\Landlord;

use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantIntegrationRequest extends FormRequest
{
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
        return [
            'integration_type' => ['required', 'string', Rule::in(['sysmo', 'gescooper'])],
            'is_active' => ['sometimes', 'boolean'],
            // Connection
            'api_url' => ['required', 'url', 'max:255'],
            'headers' => ['sometimes', 'array'],
            'headers.*.key' => ['required_with:headers.*', 'string', 'max:255'],
            'headers.*.value' => ['required_with:headers.*', 'string', 'max:1000'],
            'headers.*.enabled' => ['sometimes', 'boolean'],
            'params' => ['sometimes', 'array'],
            'params.*.key' => ['required_with:params.*', 'string', 'max:255'],
            'params.*.value' => ['required_with:params.*', 'string', 'max:1000'],
            'params.*.enabled' => ['sometimes', 'boolean'],
            'body' => ['sometimes', 'array'],
            'body.*.key' => ['required_with:body.*', 'string', 'max:255'],
            'body.*.value' => ['required_with:body.*', 'string', 'max:1000'],
            'body.*.enabled' => ['sometimes', 'boolean'],
            // Auth
            'auth_type' => ['nullable', 'string', Rule::in(['none', 'bearer', 'basic'])],
            'auth_token' => ['nullable', 'string', 'max:2000'],
            'auth_username' => ['nullable', 'string', 'max:255'],
            'auth_password' => ['nullable', 'string', 'max:255'],
            // Processing
            'sales_initial_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'products_initial_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'processing_time' => ['nullable', 'date_format:H:i'],
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
        $existingConfig = is_array($tenant?->integration?->config) ? $tenant->integration->config : [];
        $existingCredentials = is_array($existingConfig['auth']['credentials'] ?? null)
            ? $existingConfig['auth']['credentials']
            : [];

        $authType = (string) ($validated['auth_type'] ?? 'none');
        $credentials = match ($authType) {
            'bearer' => [
                'token' => (string) ($validated['auth_token'] ?? $existingCredentials['token'] ?? ''),
            ],
            'basic' => [
                'username' => (string) ($validated['auth_username'] ?? $existingCredentials['username'] ?? ''),
                'password' => (string) ($validated['auth_password'] ?? $existingCredentials['password'] ?? ''),
            ],
            default => [],
        };

        return [
            'integration_type' => (string) $validated['integration_type'],
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'config' => [
                'auth' => [
                    'type' => $authType,
                    'credentials' => $credentials,
                ],
                'connection' => [
                    'base_url' => (string) ($validated['api_url'] ?? ''),
                    'headers' => $this->buildKeyValueArray($validated['headers'] ?? []),
                    'params' => $this->buildKeyValueArray($validated['params'] ?? []),
                    'body' => $this->buildKeyValueArray($validated['body'] ?? []),
                ],
                'processing' => [
                    'sales_initial_days' => (int) ($validated['sales_initial_days'] ?? 120),
                    'products_initial_days' => (int) ($validated['products_initial_days'] ?? 120),
                    'processing_time' => (string) ($validated['processing_time'] ?? '02:00'),
                    'auto_processing_enabled' => true,
                ],
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{key: string, value: string, enabled: bool}>
     */
    private function buildKeyValueArray(array $rows): array
    {
        $result = [];

        foreach ($rows as $row) {
            if (! is_array($row) || trim((string) ($row['key'] ?? '')) === '') {
                continue;
            }

            $result[] = [
                'key' => (string) $row['key'],
                'value' => (string) ($row['value'] ?? ''),
                'enabled' => filter_var($row['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        return $result;
    }
}
