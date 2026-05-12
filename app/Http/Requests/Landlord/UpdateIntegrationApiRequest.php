<?php

namespace App\Http\Requests\Landlord;

use App\Http\Requests\Landlord\Concerns\NormalizesIntegrationApiRequests;
use App\Models\IntegrationApi;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateIntegrationApiRequest extends FormRequest
{
    use NormalizesIntegrationApiRequests;

    public function authorize(): bool
    {
        $integrationApi = $this->route('integration_api');

        return $integrationApi instanceof IntegrationApi
            && ($this->user()?->can('update', $integrationApi) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'requests_json' => ['required', 'json'],
            'response_json' => ['required', 'json'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{name: string, description: string|null, requests: array<string, mixed>, response: array<string, mixed>, is_active: bool}
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'name' => (string) $validated['name'],
            'description' => $validated['description'] ?? null,
            'requests' => $this->normalizeIntegrationApiRequests($this->decodedJson('requests_json')),
            'response' => $this->decodedJson('response_json'),
            'is_active' => $this->boolean('is_active'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodedJson(string $key): array
    {
        $decoded = json_decode((string) $this->input($key), true);

        return is_array($decoded) ? $decoded : [];
    }
}
