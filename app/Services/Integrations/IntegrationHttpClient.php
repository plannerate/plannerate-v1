<?php

namespace App\Services\Integrations;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Monta e executa requisições HTTP para uma integração.
 *
 * Recebe o config da TenantIntegration e cuida de toda a camada
 * de transporte: autenticação, headers customizados e envio.
 */
class IntegrationHttpClient
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config) {}

    /**
     * Executa a chamada HTTP para a URL com o payload fornecido.
     *
     * GET  → payload vira query string
     * POST → payload vira request body
     *
     * @param  array<string, mixed>  $payload
     */
    public function call(string $method, string $url, array $payload): Response
    {
        $http = $this->prepare();

        $response = match ($method) {
            'post' => $http->post($url, $payload),
            'put' => $http->put($url, $payload),
            'patch' => $http->patch($url, $payload),
            default => $http->get($url, $payload),
        };

        // Token em cache pode ter expirado no servidor antes do TTL local:
        // invalida para que a próxima tentativa busque um token novo.
        if ($response->status() === 401 && $this->usesFetchedBearerToken()) {
            Cache::forget($this->bearerTokenCacheKey());
        }

        return $response;
    }

    /** Prepara o PendingRequest com auth e headers. */
    private function prepare(): PendingRequest
    {
        $http = Http::timeout(config('integrations.timeout', 60))
            ->connectTimeout(10);
        $http = $this->applyAuth($http);
        $http = $this->applyHeaders($http);

        return $http;
    }

    // ─── Auth ────────────────────────────────────────────────────────────────

    /**
     * Aplica autenticação conforme o tipo configurado:
     * - basic  → HTTP Basic Auth
     * - bearer → token estático ou obtido via request separado (token_mode: fetch)
     *
     * Com `auth.token_header` preenchido, o token vai nesse header em vez de
     * `Authorization: Bearer` — algumas APIs (RP Info, p.ex.) exigem um header
     * próprio (`token: <jwt>`) e ignoram o Authorization.
     */
    private function applyAuth(PendingRequest $http): PendingRequest
    {
        $authType = (string) data_get($this->config, 'auth.type', '');

        return match ($authType) {
            'basic' => $http->withBasicAuth(
                (string) data_get($this->config, 'auth.credentials.username', ''),
                (string) data_get($this->config, 'auth.credentials.password', ''),
            ),
            'bearer' => $this->applyBearerToken($http),
            default => $http,
        };
    }

    private function applyBearerToken(PendingRequest $http): PendingRequest
    {
        $token = $this->resolveBearerToken();
        $tokenHeader = trim((string) data_get($this->config, 'auth.token_header', ''));

        return $tokenHeader !== ''
            ? $http->withHeaders([$tokenHeader => $token])
            : $http->withToken($token);
    }

    /**
     * Resolve o bearer token:
     * - token_mode "fetch" → busca via request separado, com cache curto (antes
     *   era 1 request de token POR PÁGINA buscada — multiplicador de latência
     *   e de carga na API de auth)
     * - outros             → lê diretamente de credentials.token
     */
    private function resolveBearerToken(): string
    {
        if (! $this->usesFetchedBearerToken()) {
            return (string) data_get($this->config, 'auth.credentials.token', '');
        }

        $cacheKey = $this->bearerTokenCacheKey();
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $token = $this->fetchBearerToken();

        if ($token !== '') {
            Cache::put($cacheKey, $token, (int) config('integrations.token_cache_seconds', 300));
        }

        return $token;
    }

    private function usesFetchedBearerToken(): bool
    {
        return (string) data_get($this->config, 'auth.type', '') === 'bearer'
            && (string) data_get($this->config, 'auth.token_mode', '') === 'fetch';
    }

    /** Chave por destino+credencial: integrações diferentes não compartilham token. */
    private function bearerTokenCacheKey(): string
    {
        return 'integrations:bearer-token:'.sha1(implode('|', [
            (string) data_get($this->config, 'connection.base_url', ''),
            (string) data_get($this->config, 'auth.credentials.username', ''),
        ]));
    }

    /**
     * Obtém o bearer token fazendo uma chamada separada à URL de autenticação.
     *
     * Lê de auth.token_request:
     *   method, path, username_field, password_field, response_path
     */
    private function fetchBearerToken(): string
    {
        $baseUrl = (string) data_get($this->config, 'connection.base_url', '');
        $req = data_get($this->config, 'auth.token_request', []);

        $url = rtrim($baseUrl, '/').'/'.ltrim((string) data_get($req, 'path', ''), '/');
        $method = strtolower((string) data_get($req, 'method', 'post'));

        $credentials = [
            (string) data_get($req, 'username_field', 'username') => (string) data_get($this->config, 'auth.credentials.username', ''),
            (string) data_get($req, 'password_field', 'password') => (string) data_get($this->config, 'auth.credentials.password', ''),
        ];

        $response = match ($method) {
            'get' => Http::timeout(config('integrations.timeout', 60))->connectTimeout(10)->get($url, $credentials),
            default => Http::timeout(config('integrations.timeout', 60))->connectTimeout(10)->post($url, $credentials),
        };

        if (! $response->successful()) {
            throw new RuntimeException(
                sprintf('Falha ao obter bearer token: HTTP %d em %s', $response->status(), $url)
            );
        }

        return (string) data_get($response->json(), data_get($req, 'response_path', 'token'), '');
    }

    // ─── Headers ─────────────────────────────────────────────────────────────

    /** Aplica headers customizados habilitados na integração. */
    private function applyHeaders(PendingRequest $http): PendingRequest
    {
        $headers = data_get($this->config, 'connection.headers', []);

        foreach ($headers as $header) {
            if (! ($header['enabled'] ?? false)) {
                continue;
            }

            $http = $http->withHeaders([(string) $header['key'] => (string) $header['value']]);
        }

        return $http;
    }
}
