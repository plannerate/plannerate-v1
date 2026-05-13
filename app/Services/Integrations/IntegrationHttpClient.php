<?php

namespace App\Services\Integrations;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
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

        return match ($method) {
            'post' => $http->post($url, $payload),
            'put' => $http->put($url, $payload),
            'patch' => $http->patch($url, $payload),
            default => $http->get($url, $payload),
        };
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
     */
    private function applyAuth(PendingRequest $http): PendingRequest
    {
        $authType = (string) data_get($this->config, 'auth.type', '');

        return match ($authType) {
            'basic' => $http->withBasicAuth(
                (string) data_get($this->config, 'auth.credentials.username', ''),
                (string) data_get($this->config, 'auth.credentials.password', ''),
            ),
            'bearer' => $http->withToken($this->resolveBearerToken()),
            default => $http,
        };
    }

    /**
     * Resolve o bearer token:
     * - token_mode "fetch" → busca via request separado
     * - outros             → lê diretamente de credentials.token
     */
    private function resolveBearerToken(): string
    {
        $tokenMode = (string) data_get($this->config, 'auth.token_mode', '');

        return $tokenMode === 'fetch'
            ? $this->fetchBearerToken()
            : (string) data_get($this->config, 'auth.credentials.token', '');
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
