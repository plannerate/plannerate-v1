<?php

namespace App\Http\Requests\Landlord;

use App\Models\IntegrationApi;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\QueryException;
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
            'integration_type' => ['required', 'string', Rule::in($this->integrationTypeSlugs())],
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
            'auth_type' => ['nullable', 'string', Rule::in(['none', 'bearer', 'bearer_fetch', 'basic'])],
            'auth_bearer_mode' => ['nullable', 'string', Rule::in(['manual', 'fetch'])],
            'auth_token' => ['nullable', 'string', 'max:2000'],
            'auth_username' => ['nullable', 'string', 'max:255'],
            'auth_password' => ['nullable', 'string', 'max:255'],
            'auth_token_username' => ['nullable', 'string', 'max:255'],
            'auth_token_password' => ['nullable', 'string', 'max:255'],
            'auth_token_method' => ['nullable', 'string', Rule::in(['GET', 'POST', 'PUT', 'PATCH'])],
            'auth_token_path' => ['nullable', 'string', 'max:255'],
            'auth_token_response_path' => ['nullable', 'string', 'max:255'],
            'auth_token_username_field' => ['nullable', 'string', 'max:255'],
            'auth_token_password_field' => ['nullable', 'string', 'max:255'],
            'auth_token_headers' => ['sometimes', 'array'],
            'auth_token_headers.*.key' => ['required_with:auth_token_headers.*', 'string', 'max:255'],
            'auth_token_headers.*.value' => ['required_with:auth_token_headers.*', 'string', 'max:1000'],
            'auth_token_headers.*.enabled' => ['sometimes', 'boolean'],
            'auth_token_params' => ['sometimes', 'array'],
            'auth_token_params.*.key' => ['required_with:auth_token_params.*', 'string', 'max:255'],
            'auth_token_params.*.value' => ['required_with:auth_token_params.*', 'string', 'max:1000'],
            'auth_token_params.*.enabled' => ['sometimes', 'boolean'],
            'auth_token_body' => ['sometimes', 'array'],
            'auth_token_body.*.key' => ['required_with:auth_token_body.*', 'string', 'max:255'],
            'auth_token_body.*.value' => ['required_with:auth_token_body.*', 'string', 'max:1000'],
            'auth_token_body.*.enabled' => ['sometimes', 'boolean'],
            // Processing
            'sales_initial_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'products_initial_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'processing_time' => ['nullable', 'date_format:H:i'],
            'separate_by_store' => ['sometimes', 'boolean'],
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
        $bearerMode = $authType === 'bearer_fetch'
            ? 'fetch'
            : (string) ($validated['auth_bearer_mode'] ?? $existingConfig['auth']['token_mode'] ?? 'manual');
        $usesFetchedBearer = $authType === 'bearer_fetch' || ($authType === 'bearer' && $bearerMode === 'fetch');
        $credentials = $this->credentials($authType, $usesFetchedBearer, $validated, $existingCredentials);

        return [
            'integration_type' => (string) $validated['integration_type'],
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'config' => [
                'auth' => [
                    'type' => $authType === 'bearer_fetch' ? 'bearer' : $authType,
                    'token_mode' => $authType === 'bearer' || $authType === 'bearer_fetch' ? $bearerMode : null,
                    'credentials' => $credentials,
                    'token_request' => $usesFetchedBearer ? [
                        'method' => (string) ($validated['auth_token_method'] ?? 'POST'),
                        'path' => (string) ($validated['auth_token_path'] ?? ''),
                        'response_path' => (string) ($validated['auth_token_response_path'] ?? 'token'),
                        'username_field' => (string) ($validated['auth_token_username_field'] ?? 'username'),
                        'password_field' => (string) ($validated['auth_token_password_field'] ?? 'password'),
                        'headers' => $this->buildKeyValueArray($validated['auth_token_headers'] ?? []),
                        'params' => $this->buildKeyValueArray($validated['auth_token_params'] ?? []),
                        'body' => $this->buildKeyValueArray($validated['auth_token_body'] ?? []),
                    ] : [],
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
                    'separate_by_store' => (bool) ($validated['separate_by_store'] ?? false),
                    'auto_processing_enabled' => true,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $existingCredentials
     * @return array<string, string>
     */
    private function credentials(string $authType, bool $usesFetchedBearer, array $validated, array $existingCredentials): array
    {
        if ($authType === 'bearer' && ! $usesFetchedBearer) {
            return [
                'token' => $this->sensitiveValue($validated, 'auth_token', $existingCredentials, 'token'),
            ];
        }

        if ($authType === 'basic' || $usesFetchedBearer) {
            $usernameKey = $usesFetchedBearer ? 'auth_token_username' : 'auth_username';
            $passwordKey = $usesFetchedBearer ? 'auth_token_password' : 'auth_password';

            return [
                'username' => (string) ($validated[$usernameKey] ?? $existingCredentials['username'] ?? ''),
                'password' => $this->sensitiveValue($validated, $passwordKey, $existingCredentials, 'password'),
            ];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $existingCredentials
     */
    private function sensitiveValue(array $validated, string $requestKey, array $existingCredentials, string $credentialKey): string
    {
        $value = (string) ($validated[$requestKey] ?? '');

        return $value !== '' ? $value : (string) ($existingCredentials[$credentialKey] ?? '');
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

    /**
     * @return list<string>
     */
    private function integrationTypeSlugs(): array
    {
        try {
            $database = IntegrationApi::query()
                ->where('is_active', true)
                ->pluck('id')
                ->all();
        } catch (QueryException) {
            $database = [];
        }

        return array_values(array_unique(array_filter([
            ...array_map(fn (mixed $slug): string => (string) $slug, $database),
        ], fn (string $slug): bool => $slug !== '')));
    }
}
