<?php

namespace App\Services\Integrations;

use App\Models\TenantIntegration;
use App\Services\Integrations\Auth\AuthStrategyResolver;
use App\Services\Integrations\Support\TenantIntegrationConfigNormalizer;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ExternalApiBaseService
{
    public function __construct(
        private readonly TenantIntegrationConfigNormalizer $configNormalizer,
        private readonly AuthStrategyResolver $authStrategyResolver,
    ) {}

    /**
     * @param  array<string, string|int|float|bool>  $query
     * @param  array<string, mixed>  $body
     */
    public function request(
        TenantIntegration $integration,
        string $method,
        string $endpoint,
        array $query = [],
        array $body = [],
    ): Response {
        $normalized = $this->configNormalizer->normalize($integration);
        $connection = $normalized['connection'];
        $auth = $normalized['auth'];

        if ($connection['base_url'] === '') {
            throw new RuntimeException('Base URL da integracao nao configurada.');
        }

        $request = Http::acceptJson()
            ->timeout($connection['timeout'])
            ->connectTimeout($connection['connect_timeout'])
            ->retry(1, 200, throw: false)
            ->withOptions(['verify' => $connection['verify_ssl']]);

        if ($connection['headers'] !== []) {
            $request = $request->withHeaders($connection['headers']);
        }

        $strategy = $this->authStrategyResolver->resolve($auth['type']);
        $credentials = is_array($auth['credentials'] ?? null) ? $auth['credentials'] : [];
        $request = $strategy->apply($request, $credentials);
        $query = $strategy->appendQuery($query, $credentials);

        $response = $this->sendRequest(
            $request,
            strtoupper($method),
            $this->buildUrl($connection['base_url'], $endpoint),
            $query,
            $body,
        );

        if ($response->failed()) {
            $responsePayload = $response->json();
            $responseBody = is_array($responsePayload)
                ? json_encode($responsePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : $response->body();

            throw new RuntimeException(sprintf(
                'Falha na requisicao externa: HTTP %s | URL: %s | Resposta: %s',
                $response->status(),
                $this->buildUrl($connection['base_url'], $endpoint),
                mb_substr((string) $responseBody, 0, 1000),
            ));
        }

        return $response;
    }

    private function buildUrl(string $baseUrl, string $endpoint): string
    {
        return rtrim($baseUrl, '/').'/'.ltrim($endpoint, '/');
    }

    /**
     * @param  array<string, string|int|float|bool>  $query
     * @param  array<string, mixed>  $body
     */
    private function sendRequest(
        PendingRequest $request,
        string $method,
        string $url,
        array $query,
        array $body,
    ): Response {
        return match ($method) {
            'GET' => $request->get($url, $query),
            'POST' => $request->post($url, $body),
            'PUT' => $request->put($url, $body),
            'PATCH' => $request->patch($url, $body),
            'DELETE' => $request->delete($url, $body),
            default => throw new RuntimeException('Metodo HTTP nao suportado para integracao.'),
        };
    }
}
