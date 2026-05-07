<?php

namespace App\Services\Integrations\GesCooper\Concerns;

trait ExtractsGesCooperPayloadItems
{
    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractItemsFromPayload(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        if (is_array($payload['data'] ?? null)) {
            /** @var array<int, array<string, mixed>> $data */
            $data = array_values(array_filter($payload['data'], 'is_array'));

            return $data;
        }

        return [];
    }
}
