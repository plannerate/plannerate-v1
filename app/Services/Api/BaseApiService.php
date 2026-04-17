<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Services\Api;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Serviço base para integração com APIs externas
 *
 * Fornece funcionalidades comuns para todos os serviços de API:
 * - Configuração de autenticação
 * - Tratamento de erros
 * - Logging padronizado
 * - Rate limiting
 * - Retry logic
 */
abstract class BaseApiService
{
    /**
     * Dados da integração
     */
    protected array $integration;

    /**
     * Timeout padrão para requisições (segundos)
     * Aumentado para 120s para lidar com APIs lentas/instáveis
     */
    protected int $timeout = 120;

    /**
     * Número máximo de tentativas
     * Aumentado para 5 para lidar com instabilidade de servidores externos
     */
    protected int $maxRetries = 5;

    /**
     * Delay inicial entre tentativas (segundos)
     * Usa backoff exponencial: 3s, 9s, 27s, 81s, 243s
     */
    protected int $retryDelay = 3;

    /**
     * Headers padrão para requisições
     */
    protected array $defaultHeaders = [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];

    protected array $params = [];

    /**
     * Construtor do serviço base
     */
    public function __construct(array $integration)
    {
        $this->integration = $integration;
    }

    abstract public function getEndpoint(string $name): string;

    /**
     * Método abstrato para implementar validação específica de cada serviço
     */
    abstract protected function validateIntegration(): bool;

    abstract protected function getFillableBodyFields(): array;

    /**
     * Método abstrato para extrair informações de paginação
     */
    abstract protected function extractPagination(array $response): ?array;

    public function discoverPagination($type, array $params = []): ?array
    {
        $response = $this->makeRequest($this->getEndpoint($type), $params);

        if (! $response) {
            return null;
        }

        return $response;
    }

    protected function getIntegration(): array
    {
        return $this->integration;
    }

    /**
     * Configura o cliente HTTP com autenticação e headers
     */
    protected function configureHttpClient(): PendingRequest
    {
        $client = Http::withHeaders($this->defaultHeaders)->baseUrl($this->getBaseUrl())
            ->timeout($this->timeout)
            ->retry($this->maxRetries, $this->retryDelay, function ($exception) {
                return true; // Retry on any exception
            })
            ->acceptJson();

        $client->withHeaders($this->getHeaders());
        /**
         * Verificar métodos de autenticação
         */
        if (method_exists($this, 'getBasicAuthCredentials')) {
            $credentials = $this->getBasicAuthCredentials();
            // Valida se as credenciais não são nulas antes de aplicar
            if (! empty($credentials[0]) && ! empty($credentials[1])) {
                $client->withBasicAuth(...$credentials);
            } else {
                Log::warning('Credenciais de Basic Auth vazias ou inválidas', [
                    'class' => get_class($this),
                ]);
            }
        }

        if (method_exists($this, 'getBearerToken')) {
            $token = $this->getBearerToken();
            if (! empty($token)) {
                $client->withToken($token);
            } else {
                Log::warning('Bearer token vazio ou inválido', [
                    'class' => get_class($this),
                ]);
            }
        }

        $client->withOptions($this->getOptions());

        return $client;
    }

    /**
     * Retorna a URL base da integração
     */
    public function getBaseUrl(): string
    {
        return data_get($this->integration, 'base_url', '');
    }

    /**
     * Retorna os headers da integração
     */
    protected function getHeaders(): array
    {
        return data_get($this->integration, 'headers', []);
    }

    /**
     * Retorna o método de autenticação
     */
    protected function getMethod(): string
    {
        return data_get($this->integration, 'http_method', 'GET');
    }

    /**
     * Retorna os params para metodos de requisição
     */
    protected function getParams(): array
    {
        return [];
    }

    /**
     * Retorna o corpo da requisição
     */
    protected function getBody(): array
    {
        return [];
    }

    /**
     * Retorna as opções para a requisição
     */
    protected function getOptions(): array
    {
        return [];
    }

    /**
     * Faz uma requisição HTTP com retry automático e backoff exponencial
     */
    protected function makeRequest(string $endpoint, array $params = []): ?array
    {
        $attempt = 1;
        $lastException = null;

        while ($attempt <= $this->maxRetries) {
            try {
                $client = $this->configureHttpClient();

                $response = match ($this->getMethod()) {
                    'GET' => $client->get($endpoint, array_merge($this->getParams(), $params)),
                    'POST' => $client->post($endpoint, array_merge($this->getBody(), $params)),
                    'PUT' => $client->put($endpoint, array_merge($this->getBody(), $params)),
                    'DELETE' => $client->delete($endpoint, array_merge($this->getParams(), $params)),
                    default => throw new \Exception("Método HTTP {$this->getMethod()} não suportado"),
                };

                return $this->handleResponse($response);
            } catch (\Exception $e) {
                $lastException = $e;
                $isRetryable = $this->isRetryableError($e);

                // Captura resposta completa se for RequestException
                $errorDetails = [
                    'endpoint' => $endpoint,
                    'message' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'attempt' => $attempt,
                    'max_retries' => $this->maxRetries,
                    'is_retryable' => $isRetryable,
                ];

                // Se for RequestException do Laravel HTTP Client
                if ($e instanceof \Illuminate\Http\Client\RequestException) {
                    $response = $e->response;
                    $errorDetails['status_code'] = $response->status();
                    $errorDetails['response_body'] = $response->body();
                    $errorDetails['response_json'] = $response->json();
                }
                // Se for RequestException do Guzzle
                elseif ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
                    $response = $e->getResponse();
                    $errorDetails['status_code'] = $response->getStatusCode();
                    $errorDetails['response_body'] = (string) $response->getBody();
                }

                // Se não for retryable ou já esgotou tentativas, loga como erro final
                if (!$isRetryable || $attempt >= $this->maxRetries) {
                    Log::error('Erro final em makeRequest após tentativas', $errorDetails);
                    break;
                }

                // Log de warning para tentativas intermediárias
                Log::warning('Erro temporário em makeRequest, tentando novamente', $errorDetails);

                // Backoff exponencial: 3s, 9s, 27s, 81s, 243s
                $backoffDelay = $this->retryDelay * pow(3, $attempt - 1);
                sleep($backoffDelay);

                $attempt++;
            }
        }

        // Após esgotar todas as tentativas, lança exceção
        if ($lastException) {
            throw new \Exception(
                "Falha na requisição após {$this->maxRetries} tentativas: {$lastException->getMessage()}",
                0,
                $lastException
            );
        }

        // Caso improvável: sem tentativas e sem exceção (nunca deve acontecer)
        throw new \Exception('Falha inesperada na requisição: nenhuma tentativa executada');
    }

    /**
     * Verifica se um erro é retryable (temporário)
     */
    protected function isRetryableError(\Exception $e): bool
    {
        // Timeout é sempre retryable
        if ($e instanceof \Illuminate\Http\Client\ConnectionException) {
            return true;
        }

        // Request exceptions com status 5xx ou erros específicos do servidor
        if ($e instanceof \Illuminate\Http\Client\RequestException) {
            $status = $e->response->status();
            $body = $e->response->body();

            // Status 5xx são erros do servidor (retryable)
            if ($status >= 500) {
                // Erros específicos que indicam instabilidade temporária
                if (
                    str_contains($body, 'Access violation') ||
                    str_contains($body, 'Cannot perform the action, because the previous action is in progress') ||
                    str_contains($body, 'FireDAC')
                ) {
                    return true;
                }

                return true; // Outros 5xx também são retryable
            }

            // Status 429 (Too Many Requests) é retryable
            if ($status === 429) {
                return true;
            }
        }

        return false;
    }

    /**
     * Trata a resposta da requisição
     */
    protected function handleResponse(Response $response): ?array
    {

        if ($response->successful()) {
            return $this->extractPagination($response->json());
        }

        Log::error('Erro na requisição API', [
            'status' => $response->status(),
            'body' => $response->body(),
            'headers' => $response->headers(),
        ]);

        return [
            'page' => 0,
            'per_page' => 0,
            'total' => 0,
            'data' => [],
        ];
    }
}
