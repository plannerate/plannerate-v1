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
        /** @var Tenant|null $tenant */
        $tenant = $this->route('tenant');
        $integrationExists = $tenant?->integration()->exists() ?? false;
        $type = (string) $this->input('integration_type', 'sysmo');

        $shared = [
            'integration_type' => ['required', 'string', Rule::in(['sysmo', 'gescooper'])],
            'identifier' => ['nullable', 'string', 'max:255'],
            'auto_processing_enabled' => ['sometimes', 'boolean'],
            'processing_time' => ['nullable', 'date_format:H:i'],
            'initial_setup_date' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'products_page_size' => ['nullable', 'integer', 'min:1', 'max:50000'],
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
            'default_body' => ['nullable', 'string', 'max:10000'],
            // Auth
            'auth_type' => ['nullable', 'string', Rule::in(['none', 'bearer', 'basic', 'api_key_header', 'api_key_query', 'gescooper'])],
            'auth_token' => ['nullable', 'string', 'max:2000'],
            'auth_api_key' => ['nullable', 'string', 'max:2000'],
            'auth_api_key_name' => ['nullable', 'string', 'max:255'],
            'auth_username' => ['nullable', 'string', 'max:255'],
            'auth_password' => ['nullable', 'string', 'max:255'],
        ];

        if ($type === 'gescooper') {
            return array_merge($shared, [
                'usuario' => ['required', 'string', 'max:255'],
                'senha' => [
                    Rule::requiredIf(! $integrationExists),
                    'nullable',
                    'string',
                    'max:255',
                ],
                'dispositivo_uid' => ['nullable', 'string', 'max:255'],
            ]);
        }

        // Sysmo
        return array_merge($shared, [
            'external_name' => ['required', 'string', 'max:255'],
            'partner_key' => ['required', 'string', 'max:255'],
            'empresa' => ['nullable', 'string', 'max:255'],
            'external_name_ean' => ['nullable', 'string', 'max:255'],
            'external_name_status' => ['nullable', 'string', 'max:255'],
            'external_name_sale_date' => ['nullable', 'string', 'max:255'],
            'days_to_maintain' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'sales_initial_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'products_initial_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'daily_lookback_days' => ['nullable', 'integer', 'min:2', 'max:365'],
            'sales_page_size' => ['nullable', 'integer', 'min:1', 'max:50000'],
            'sales_tipo_consulta' => ['nullable', 'string', 'max:50'],
            'connection_timeout' => ['nullable', 'integer', 'min:1', 'max:120'],
            'connection_connect_timeout' => ['nullable', 'integer', 'min:1', 'max:120'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function integrationPayload(): array
    {
        $validated = $this->validated();
        $type = (string) ($validated['integration_type'] ?? 'sysmo');

        if ($type === 'gescooper') {
            return $this->gesCooperPayload($validated);
        }

        return $this->sysmoPayload($validated);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function gesCooperPayload(array $validated): array
    {
        /** @var Tenant|null $tenant */
        $tenant = $this->route('tenant');
        $existingConfig = is_array($tenant?->integration?->config) ? $tenant->integration->config : [];
        $existingCredentials = is_array($existingConfig['auth']['credentials'] ?? null)
            ? $existingConfig['auth']['credentials']
            : [];
        $existingConnection = is_array($existingConfig['connection'] ?? null)
            ? $existingConfig['connection']
            : [];

        $authType = (string) ($validated['auth_type'] ?? 'gescooper');
        $resolvedSenha = $validated['senha'] ?? $existingCredentials['senha'] ?? '';

        return [
            'integration_type' => 'gescooper',
            'identifier' => $validated['identifier'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'config' => [
                'auth' => [
                    'type' => $authType,
                    'credentials' => [
                        'usuario' => (string) ($validated['usuario'] ?? ''),
                        'senha' => (string) $resolvedSenha,
                        'dispositivo_uid' => (string) ($validated['dispositivo_uid'] ?? ''),
                    ],
                ],
                'connection' => [
                    'base_url' => (string) ($validated['api_url'] ?? ''),
                    'timeout' => (int) ($existingConnection['timeout'] ?? 30),
                    'connect_timeout' => (int) ($existingConnection['connect_timeout'] ?? 10),
                    'verify_ssl' => (bool) ($existingConnection['verify_ssl'] ?? true),
                    'ping_path' => (string) ($existingConnection['ping_path'] ?? '/v1/Token'),
                    'ping_method' => 'POST',
                    'headers' => $this->buildKeyValueArray($validated['headers'] ?? []),
                    'params' => $this->buildKeyValueArray($validated['params'] ?? []),
                    'default_body' => $validated['default_body'] ?? null,
                ],
                'processing' => [
                    'products_page_size' => (int) ($validated['products_page_size'] ?? 200),
                    'auto_processing_enabled' => (bool) ($validated['auto_processing_enabled'] ?? true),
                    'processing_time' => (string) ($validated['processing_time'] ?? '02:00'),
                    'initial_setup_date' => $validated['initial_setup_date'] ?? null,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function sysmoPayload(array $validated): array
    {
        /** @var Tenant|null $tenant */
        $tenant = $this->route('tenant');
        $existingIntegration = $tenant?->integration;
        $existingConfig = is_array($existingIntegration?->config) ? $existingIntegration->config : [];
        $existingAuthConfig = is_array($existingConfig['auth'] ?? null) ? $existingConfig['auth'] : [];
        $existingConnectionConfig = is_array($existingConfig['connection'] ?? null) ? $existingConfig['connection'] : [];
        $existingCredentials = is_array($existingAuthConfig['credentials'] ?? null) ? $existingAuthConfig['credentials'] : [];

        $resolvedPassword = $validated['auth_password'] ?? $existingCredentials['password'] ?? '';
        $authType = (string) ($validated['auth_type'] ?? $existingAuthConfig['type'] ?? 'basic');
        $authCredentials = $this->resolveAuthCredentials($authType, $validated, $existingAuthConfig, (string) $resolvedPassword);

        return [
            'integration_type' => 'sysmo',
            'identifier' => $validated['identifier'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
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
                    'partner_key' => $validated['partner_key'] ?? '',
                    'empresa' => $validated['empresa'] ?? $validated['identifier'] ?? '',
                    'auto_processing_enabled' => (bool) ($validated['auto_processing_enabled'] ?? true),
                    'processing_time' => $validated['processing_time'] ?? '02:00',
                    'initial_setup_date' => $validated['initial_setup_date'] ?? null,
                ],
                'auth' => [
                    'type' => $authType,
                    'credentials' => $authCredentials,
                ],
                'connection' => [
                    'base_url' => (string) ($validated['api_url'] ?? ''),
                    'timeout' => (int) ($validated['connection_timeout'] ?? $existingConnectionConfig['timeout'] ?? 30),
                    'connect_timeout' => (int) ($validated['connection_connect_timeout'] ?? $existingConnectionConfig['connect_timeout'] ?? 10),
                    'verify_ssl' => (bool) ($existingConnectionConfig['verify_ssl'] ?? true),
                    'ping_path' => (string) ($existingConnectionConfig['ping_path'] ?? '/'),
                    'ping_method' => (string) ($existingConnectionConfig['ping_method'] ?? 'GET'),
                    'headers' => $this->buildKeyValueArray($validated['headers'] ?? []),
                    'params' => $this->buildKeyValueArray($validated['params'] ?? []),
                    'default_body' => $validated['default_body'] ?? null,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $existingAuthConfig
     * @return array<string, mixed>
     */
    private function resolveAuthCredentials(
        string $authType,
        array $validated,
        array $existingAuthConfig,
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
                'prefix' => (string) ($existingCredentials['prefix'] ?? ''),
            ],
            'api_key_query' => [
                'name' => (string) ($validated['auth_api_key_name'] ?? $existingCredentials['name'] ?? 'api_key'),
                'key' => (string) ($validated['auth_api_key'] ?? $existingCredentials['key'] ?? ''),
            ],
            default => [
                'username' => (string) ($validated['auth_username'] ?? $existingCredentials['username'] ?? ''),
                'password' => $resolvedPassword,
            ],
        };
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
