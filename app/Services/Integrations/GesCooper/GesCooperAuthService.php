<?php

namespace App\Services\Integrations\GesCooper;

use App\Models\TenantIntegration;
use App\Services\Integrations\Support\TenantIntegrationConfigNormalizer;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GesCooperAuthService
{
    private ?string $cachedToken = null;

    public function __construct(
        private readonly TenantIntegrationConfigNormalizer $configNormalizer,
        private readonly GesCooperEndpoints $endpoints,
    ) {}

    public function getToken(TenantIntegration $integration): string
    {
        if ($this->cachedToken !== null) {
            return $this->cachedToken;
        }

        $normalized = $this->configNormalizer->normalize($integration);
        $baseUrl = rtrim($normalized['connection']['base_url'], '/');
        $credentials = $normalized['auth']['credentials'];

        if ($baseUrl === '') {
            throw new RuntimeException('GesCooper: base_url nao configurada.');
        }

        $response = Http::acceptJson()
            ->timeout($normalized['connection']['timeout'])
            ->connectTimeout($normalized['connection']['connect_timeout'])
            ->withOptions(['verify' => $normalized['connection']['verify_ssl']])
            ->post($baseUrl.'/'.$this->endpoints->get('token'), [
                'usuario' => (string) ($credentials['usuario'] ?? $credentials['username'] ?? ''),
                'senha' => (string) ($credentials['senha'] ?? $credentials['password'] ?? ''),
                'dispositivoUID' => (string) ($credentials['dispositivo_uid'] ?? ''),
            ]);

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'GesCooper: falha na autenticacao. HTTP %s: %s',
                $response->status(),
                mb_substr($response->body(), 0, 500),
            ));
        }

        $json = $response->json();
        $token = is_array($json) ? ($json['token'] ?? $json['access_token'] ?? null) : null;

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('GesCooper: token nao encontrado na resposta de autenticacao. Campos disponiveis: '.implode(', ', is_array($json) ? array_keys($json) : []));
        }

        $this->cachedToken = $token;

        return $this->cachedToken;
    }
}
