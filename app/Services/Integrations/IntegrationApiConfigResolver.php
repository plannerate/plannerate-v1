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
        return $this->databaseProvider($slug);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function options(): array
    {
        try {
            return IntegrationApi::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['name', 'slug'])
                ->map(fn (IntegrationApi $api): array => [
                    'value' => $api->slug,
                    'label' => $api->name,
                ])
                ->all();
        } catch (QueryException) {
            return [];
        }
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
