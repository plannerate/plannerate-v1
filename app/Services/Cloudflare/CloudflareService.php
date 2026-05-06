<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Services\Cloudflare;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Serviço para integração com a API Cloudflare (DNS).
 *
 * @see https://developers.cloudflare.com/api/
 */
class CloudflareService
{
    protected string $baseUri;

    protected string $apiToken;

    public function __construct(?string $apiToken = null, ?string $baseUri = null)
    {
        $raw = $apiToken ?? config('cloudflare.api_token', '');
        $this->apiToken = $this->normalizeToken($raw);
        $this->baseUri = rtrim($baseUri ?? config('cloudflare.base_uri', 'https://api.cloudflare.com/client/v4'), '/');
    }

    /**
     * Normaliza o token: trim e remove "Bearer " no início (evita duplicar no header).
     * A API Cloudflare espera apenas o token no header "Authorization: Bearer {token}".
     */
    protected function normalizeToken(string $token): string
    {
        $token = trim($token);
        if (str_starts_with($token, 'Bearer ')) {
            $token = trim(substr($token, 7));
        }

        return $token;
    }

    public function isConfigured(): bool
    {
        return $this->apiToken !== '';
    }

    /**
     * @return array{success: bool, result?: array, errors?: array, messages?: array}
     */
    protected function request(string $method, string $path, array $data = []): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'errors' => [['message' => 'Cloudflare API token not configured.']],
            ];
        }

        $url = $this->baseUri.'/'.ltrim($path, '/');

        try {
            $request = Http::withToken($this->apiToken)
                ->acceptJson()
                ->contentType('application/json')
                ->timeout(config('cloudflare.timeout', 30));

            $response = match (strtoupper($method)) {
                'GET' => $request->get($url, $data),
                'POST' => $request->post($url, $data),
                'PUT' => $request->put($url, $data),
                'PATCH' => $request->patch($url, $data),
                'DELETE' => $request->delete($url),
                default => throw new \InvalidArgumentException("Unsupported method: {$method}"),
            };

            $body = $response->json() ?? ['success' => false, 'errors' => [['message' => 'Invalid JSON response']]];

            if (! $response->successful()) {
                Log::warning('Cloudflare API error', [
                    'method' => $method,
                    'path' => $path,
                    'status' => $response->status(),
                    'body' => $body,
                ]);
            }

            return $body;
        } catch (RequestException $e) {
            Log::error('Cloudflare API request failed', [
                'method' => $method,
                'path' => $path,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'errors' => [['message' => $e->getMessage()]],
            ];
        }
    }

    /**
     * Lista zones (domínios) da conta.
     *
     * @return array{success: bool, result?: array<int, array{id: string, name: string, status: string, ...}>, errors?: array}
     */
    public function listZones(?string $name = null, int $page = 1, int $perPage = 50): array
    {
        $params = array_filter([
            'page' => $page,
            'per_page' => $perPage,
            'name' => $name,
        ]);

        return $this->request('GET', 'zones', $params);
    }

    /**
     * Lista registros DNS de uma zone.
     *
     * @return array{success: bool, result?: array, errors?: array}
     */
    public function listRecords(string $zoneId, ?string $type = null, ?string $name = null, int $page = 1, int $perPage = 100): array
    {
        $params = array_filter([
            'page' => $page,
            'per_page' => $perPage,
            'type' => $type,
            'name' => $name,
        ]);

        return $this->request('GET', "zones/{$zoneId}/dns_records", $params);
    }

    /**
     * Cria um registro DNS.
     *
     * @param  array{type: string, name: string, content: string, ttl?: int, proxied?: bool, comment?: string}  $payload
     * @return array{success: bool, result?: array{id: string, type: string, name: string, content: string, ...}, errors?: array}
     */
    public function createRecord(string $zoneId, array $payload): array
    {
        $body = array_filter([
            'type' => $payload['type'] ?? null,
            'name' => $payload['name'] ?? null,
            'content' => $payload['content'] ?? null,
            'ttl' => $payload['ttl'] ?? 1,
            'proxied' => $payload['proxied'] ?? false,
            'comment' => $payload['comment'] ?? null,
        ], fn ($v) => $v !== null);

        return $this->request('POST', "zones/{$zoneId}/dns_records", $body);
    }

    /**
     * Remove um registro DNS.
     *
     * @return array{success: bool, result?: array{id: string}, errors?: array}
     */
    public function deleteRecord(string $zoneId, string $recordId): array
    {
        return $this->request('DELETE', "zones/{$zoneId}/dns_records/{$recordId}");
    }
}