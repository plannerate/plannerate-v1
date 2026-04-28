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
            'sales_initial_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'products_initial_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'daily_lookback_days' => ['nullable', 'integer', 'min:2', 'max:365'],
            'sales_page_size' => ['nullable', 'integer', 'min:1', 'max:50000'],
            'products_page_size' => ['nullable', 'integer', 'min:1', 'max:50000'],
            'sales_tipo_consulta' => ['nullable', 'string', 'max:50'],
            'auto_processing_enabled' => ['sometimes', 'boolean'],
            'processing_time' => ['nullable', 'date_format:H:i'],
            'initial_setup_date' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'auth_type' => ['nullable', 'string', Rule::in(['none', 'bearer', 'basic', 'api_key_header', 'api_key_query', 'custom_headers'])],
            'auth_token' => ['nullable', 'string', 'max:2000'],
            'auth_api_key' => ['nullable', 'string', 'max:2000'],
            'auth_api_key_name' => ['nullable', 'string', 'max:255'],
            'auth_api_key_prefix' => ['nullable', 'string', 'max:50'],
            'connection_timeout' => ['nullable', 'integer', 'min:1', 'max:120'],
            'connection_connect_timeout' => ['nullable', 'integer', 'min:1', 'max:120'],
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
        $existingConfig = is_array($existingIntegration?->config) ? $existingIntegration->config : [];
        $existingAuthConfig = is_array($existingConfig['auth'] ?? null) ? $existingConfig['auth'] : [];
        $existingConnectionConfig = is_array($existingConfig['connection'] ?? null) ? $existingConfig['connection'] : [];
        $resolvedPassword = $validated['auth_password'] ?? $existingHeaders['auth_password'] ?? '';

        $authType = (string) ($validated['auth_type'] ?? $existingAuthConfig['type'] ?? 'basic');
        $authCredentials = $this->resolveAuthCredentials(
            $authType,
            $validated,
            $existingAuthConfig,
            $existingHeaders,
            (string) $resolvedPassword,
        );

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
                'processing' => [
                    'days_to_maintain' => $validated['days_to_maintain'] ?? 120,
                    'sales_retention_days' => $validated['days_to_maintain'] ?? 120,
                    'sales_initial_days' => $validated['sales_initial_days'] ?? $validated['days_to_maintain'] ?? 120,
                    'products_initial_days' => $validated['products_initial_days'] ?? $validated['days_to_maintain'] ?? 120,
                    'daily_lookback_days' => $validated['daily_lookback_days'] ?? 7,
                    'sales_page_size' => $validated['sales_page_size'] ?? 20000,
                    'products_page_size' => $validated['products_page_size'] ?? 1000,
                    'sales_tipo_consulta' => $validated['sales_tipo_consulta'] ?? 'produto',
                    'partner_key' => $validated['partner_key'],
                    'empresa' => $validated['empresa'] ?? $validated['identifier'],
                    'auto_processing_enabled' => (bool) ($validated['auto_processing_enabled'] ?? true),
                    'processing_time' => $validated['processing_time'] ?? '02:00',
                    'initial_setup_date' => $validated['initial_setup_date'] ?? null,
                ],
                'auth' => [
                    'type' => $authType,
                    'credentials' => $authCredentials,
                ],
                'connection' => [
                    'base_url' => $validated['api_url'],
                    'timeout' => (int) ($validated['connection_timeout'] ?? $existingConnectionConfig['timeout'] ?? 30),
                    'connect_timeout' => (int) ($validated['connection_connect_timeout'] ?? $existingConnectionConfig['connect_timeout'] ?? 10),
                    'verify_ssl' => (bool) ($existingConnectionConfig['verify_ssl'] ?? true),
                    'ping_path' => (string) ($existingConnectionConfig['ping_path'] ?? '/'),
                    'ping_method' => (string) ($existingConnectionConfig['ping_method'] ?? 'GET'),
                    'headers' => is_array($existingConnectionConfig['headers'] ?? null) ? $existingConnectionConfig['headers'] : [],
                ],
            ],
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $existingAuthConfig
     * @param  array<string, mixed>  $existingHeaders
     * @return array<string, mixed>
     */
    private function resolveAuthCredentials(
        string $authType,
        array $validated,
        array $existingAuthConfig,
        array $existingHeaders,
        string $resolvedPassword,
    ): array {
        $existingCredentials = is_array($existingAuthConfig['credentials'] ?? null) ? $existingAuthConfig['credentials'] : [];

        return match ($authType) {
            'none' => [],
            'bearer' => [
                'token' => (string) ($validated['auth_token'] ?? $existingCredentials['token'] ?? ''),
            ],
            'api_key_header' => [
                'name' => (string) ($validated['auth_api_key_name'] ?? $existingCredentials['name'] ?? 'X-API-KEY'),
                'key' => (string) ($validated['auth_api_key'] ?? $existingCredentials['key'] ?? ''),
                'prefix' => (string) ($validated['auth_api_key_prefix'] ?? $existingCredentials['prefix'] ?? ''),
            ],
            'api_key_query' => [
                'name' => (string) ($validated['auth_api_key_name'] ?? $existingCredentials['name'] ?? 'api_key'),
                'key' => (string) ($validated['auth_api_key'] ?? $existingCredentials['key'] ?? ''),
            ],
            'custom_headers' => is_array($existingCredentials['headers'] ?? null) ? [
                'headers' => $existingCredentials['headers'],
            ] : [
                'headers' => $existingHeaders,
            ],
            default => [
                'username' => (string) ($validated['auth_username'] ?? $existingCredentials['username'] ?? ''),
                'password' => $resolvedPassword,
            ],
        };
    }
}
