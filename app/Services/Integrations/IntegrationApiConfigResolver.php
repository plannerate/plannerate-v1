<?php

namespace App\Services\Integrations;

use App\Models\IntegrationApi;
use Illuminate\Database\QueryException;

class IntegrationApiConfigResolver
{
    /**
     * @return array<string, mixed>
     */
    public function provider(string $slug): array
    {
        $databaseConfig = $this->databaseProvider($slug);

        if ($databaseConfig !== []) {
            return $databaseConfig;
        }

        $config = config(sprintf('integrations.providers.%s', $slug), []);

        return is_array($config) ? $config : [];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function options(): array
    {
        try {
            $databaseOptions = IntegrationApi::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['name', 'slug'])
                ->map(fn (IntegrationApi $api): array => [
                    'value' => $api->slug,
                    'label' => $api->name,
                ])
                ->all();
        } catch (QueryException) {
            $databaseOptions = [];
        }

        $configuredOptions = collect([
            ...config('integrations.providers', []),
            'generic' => [],
        ])
            ->filter(fn (mixed $provider, string|int $slug): bool => is_string($slug) && is_array($provider))
            ->map(fn (mixed $provider, string $slug): array => [
                'value' => $slug,
                'label' => __("app.landlord.tenant_integrations.types.{$slug}"),
            ])
            ->values()
            ->all();

        return collect([...$databaseOptions, ...$configuredOptions])
            ->unique('value')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseProvider(string $slug): array
    {
        try {
            $api = IntegrationApi::query()
                ->where('slug', $slug)
                ->where('is_active', true)
                ->first();
        } catch (QueryException) {
            return [];
        }

        if (! $api instanceof IntegrationApi) {
            return [];
        }

        return [
            'requests' => is_array($api->requests) ? $api->requests : [],
            'response' => is_array($api->response) ? $api->response : [],
        ];
    }
}
