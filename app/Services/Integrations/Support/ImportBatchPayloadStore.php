<?php

namespace App\Services\Integrations\Support;

use Illuminate\Support\Facades\Storage;

class ImportBatchPayloadStore
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function put(string $integrationId, string $resource, array $items): string
    {
        $key = sprintf(
            'imports/batches/%s/%s/%s.json',
            $resource,
            $integrationId,
            (string) str()->ulid(),
        );

        Storage::disk('local')->put($key, json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $key;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pull(string $key): array
    {
        if (! Storage::disk('local')->exists($key)) {
            return [];
        }

        $payload = Storage::disk('local')->get($key);
        Storage::disk('local')->delete($key);

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : [];
    }
}
