<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Services\Api\Visao;

use App\Services\Api\BaseApiService;
use Illuminate\Support\Facades\Log;

/**
 * Serviço para integração com a API da Visão
 *
 * Implementa todas as funcionalidades específicas da API Visão:
 * - Autenticação via Basic Auth
 * - Estrutura de resposta específica da Visão
 * - Endpoints para produtos, vendas, etc.
 * - Paginação sem total de páginas (continua até retornar vazio ou erro 400)
 */
class VisaoApiService extends BaseApiService
{
    /**
     * Timeout específico para Visão (60 segundos)
     */
    protected int $timeout = 60;

    /**
     * Headers específicos da Visão
     */
    protected array $defaultHeaders = [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];

    /**
     * Endpoints disponíveis na API Visão
     */
    private const ENDPOINTS = [
        'products' => 'plannerate/produtos',
        'product' => 'plannerate/produtos',
        'sales' => 'plannerate/vendas',
    ];

    /**
     * Descobre paginação buscando página por página até encontrar o fim
     * A API Visão não retorna total de páginas, então continua até:
     * - Receber array vazio
     * - Receber erro 400 com "Nenhum produto foi encontrado"
     * - Receber erro
     * Despacha ProcessIntegrationDataJob a cada página para evitar jobs gigantes
     *
     * @param  string  $type  Tipo de dados (products, sales, purchase)
     * @param  array  $params  Parâmetros da requisição
     * @param  int|null  $maxPages  Limite de páginas (null = sem limite)
     * @param  callable|null  $callback  Callback para processar cada página (recebe $data, $pageNumber)
     */
    public function discoverPagination($type, array $params = [], ?int $maxPages = null, ?callable $callback = null): array
    {
        Log::info('Requisição feita para VisaoApiService discoverPagination', [
            'type' => $type,
            'params' => $params,
            'max_pages' => $maxPages,
        ]);

        $totalItems = 0;
        $pagesProcessed = 0;
        $pagina = 1;
        $porPagina = $params['registros_por_pagina'] ?? 1000;
        $temMaisPaginas = true;

        while ($temMaisPaginas) {
            // Verifica limitador de páginas
            if ($maxPages !== null && $pagina > $maxPages) {
                Log::info("Limite de páginas atingido: {$maxPages}");
                break;
            }

            Log::info("Buscando {$type} - Página {$pagina}".($maxPages ? " (limite: {$maxPages})" : ''));

            $paginaParams = array_merge($params, [
                'pagina' => $pagina,
                'registros_por_pagina' => $porPagina,
            ]);

            $response = $this->makeRequest($this->getEndpoint($type), $paginaParams);

            if (! $response) {
                Log::warning("Sem resposta na página {$pagina}. Encerrando paginação.");
                $temMaisPaginas = false;
                break;
            }

            $data = $response['data'] ?? [];
            $qtd = is_array($data) ? count($data) : 0;

            Log::info("Página {$pagina} - Itens retornados: {$qtd}");

            // Se recebeu dados, processa via callback
            if (! empty($data)) {
                $totalItems += $qtd;
                $pagesProcessed++;

                // Executa callback se fornecido (para despachar job de processamento)
                if ($callback) {
                    $callback($data, $pagina);
                }

                $pagina++;
                // Pequeno delay para não sobrecarregar a API
                usleep(200000); // 200ms
            } else {
                // Sem dados, encerra paginação
                Log::info("Fim da paginação na página {$pagina} - Nenhum item encontrado");
                $temMaisPaginas = false;
            }
        }

        Log::info('Paginação concluída', [
            'total_items' => $totalItems,
            'pages_processed' => $pagesProcessed,
        ]);

        return [
            'total_items' => $totalItems,
            'pages_processed' => $pagesProcessed,
        ];
    }

    /**
     * Busca vendas de um dia específico com paginação alta (10k registros)
     *
     * @param  string  $type  Tipo de dados (sales, products)
     * @param  string  $dateField  Nome do campo de data na API (data_venda para Visão)
     * @param  string  $date  Data no formato Y-m-d
     * @param  array  $params  Parâmetros adicionais
     * @param  callable|null  $callback  Callback para processar dados
     * @return array Total de itens e páginas processadas
     */
    public function discoverDay(string $type, string $dateField, string $date, array $params = [], ?callable $callback = null): array
    {
        Log::info("Buscando {$type} do dia {$date}");

        $totalItems = 0;
        $pagesProcessed = 0;
        $pagina = 1;
        $porPagina = 10000; // Paginação alta para garantir tudo em 1 página

        $paginaParams = array_merge($params, [
            $dateField => $date, // data_venda para Visão
            'pagina' => $pagina,
            'registros_por_pagina' => $porPagina,
        ]);

        $response = $this->makeRequest($this->getEndpoint($type), $paginaParams);

        // Se não obteve resposta ou resposta inválida, retorna vazio (dia sem vendas)
        if (! $response || ! is_array($response)) {
            Log::info("Dia {$date} sem dados ou erro na requisição");

            return [
                'total_items' => 0,
                'pages_processed' => 0,
            ];
        }

        $data = $response['data'] ?? [];
        $qtd = is_array($data) ? count($data) : 0;

        if (! empty($data)) {
            $totalItems = $qtd;
            $pagesProcessed = 1;

            Log::info('DEBUG: Dados recebidos', [
                'date' => $date,
                'items_count' => $qtd,
                'has_callback' => $callback !== null,
                'sample_item' => isset($data[0]) ? array_keys($data[0]) : 'no items',
            ]);

            if ($callback) {
                $callback($data, $date, $pagina);
            }
        }

        // Log silencioso - apenas em caso de erro

        return [
            'total_items' => $totalItems,
            'pages_processed' => $pagesProcessed,
        ];
    }

    /**
     * Busca vendas dia por dia em um range de datas
     *
     * @param  string  $type  Tipo de dados (sales)
     * @param  string  $dateField  Nome do campo de data (data_venda)
     * @param  string  $startDate  Data inicial (Y-m-d)
     * @param  string  $endDate  Data final (Y-m-d)
     * @param  array  $params  Parâmetros adicionais
     * @param  callable|null  $callback  Callback para processar cada dia
     * @return array Resumo da busca
     */
    public function discoverDateRange(string $type, string $dateField, string $startDate, string $endDate, array $params = [], ?callable $callback = null): array
    {
        Log::info('Iniciando busca dia a dia', [
            'type' => $type,
            'start' => $startDate,
            'end' => $endDate,
        ]);

        $totalItems = 0;
        $daysProcessed = 0;
        $currentDate = \Carbon\Carbon::parse($startDate);
        $endDateCarbon = \Carbon\Carbon::parse($endDate);

        while ($currentDate->lte($endDateCarbon)) {
            $dateStr = $currentDate->format('Y-m-d');

            $result = $this->discoverDay(
                type: $type,
                dateField: $dateField,
                date: $dateStr,
                params: $params,
                callback: $callback
            );

            $totalItems += $result['total_items'];
            $daysProcessed++;

            $currentDate->addDay();
            usleep(500000); // 500ms entre dias
        }

        Log::info('Busca dia a dia concluída', [
            'total_items' => $totalItems,
            'days_processed' => $daysProcessed,
        ]);

        return [
            'total_items' => $totalItems,
            'days_processed' => $daysProcessed,
        ];
    }

    public function fetchData(string $type, array $params = []): ?array
    {
        $response = $this->makeRequest($this->getEndpoint($type), $params);

        if (! $response) {
            return null;
        }

        return $this->extractPagination($response);
    }

    public function getEndpoint(string $name): string
    {
        if (! array_key_exists($name, self::ENDPOINTS)) {
            throw new \InvalidArgumentException("Endpoint {$name} não encontrado na API Visão.");
        }

        return self::ENDPOINTS[$name];
    }

    /**
     * Extrai informações de paginação da resposta
     * A API Visão retorna os dados em 'dados' e não informa total de páginas
     */
    protected function extractPagination(array $response): ?array
    {
        if (empty($response)) {
            return null;
        }

        // A API Visão retorna os dados em 'dados'
        $dados = data_get($response, 'dados', []);

        return [
            'page' => 1,
            'per_page' => count($dados),
            'total' => count($dados),
            'data' => $dados,
        ];
    }

    /**
     * Método HTTP usado pela API Visão
     */
    protected function getMethod(): string
    {
        return 'POST';
    }

    /**
     * Valida se a integração Visão está configurada corretamente
     */
    public function validateIntegration(): bool
    {
        // Verifica se é uma integração Visão
        if (data_get($this->integration, 'integration_type') !== 'visao') {
            Log::error('Tipo de integração incorreto para VisaoApiService', [
                'expected' => 'visao',
                'actual' => data_get($this->integration, 'integration_type'),
            ]);

            return false;
        }

        // Verifica se tem URL da API configurada
        if (empty(data_get($this->integration, 'base_url')) && empty(data_get($this->integration, 'api_url'))) {
            Log::error('URL da API não configurada');

            return false;
        }

        // Verifica se tem credenciais de autenticação
        $username = data_get($this->integration, 'headers.authorization.username');
        $password = data_get($this->integration, 'headers.authorization.password');

        if (empty($username) || empty($password)) {
            Log::error('Autenticação não configurada corretamente', [
                'has_username' => ! empty($username),
                'has_password' => ! empty($password),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Método de autenticação via Basic Auth
     */
    public function getBasicAuthCredentials(): array
    {
        $username = data_get($this->integration, 'headers.authorization.username');
        $password = data_get($this->integration, 'headers.authorization.password');

        Log::info('Obtendo credenciais de autenticação básica para Visão', [
            'integration' => [
                'username' => $username,
                'password' => $password ? '***' : null,
            ],
        ]);

        // Retorna array indexado para funcionar com spread operator (...)
        return [$username, $password];
    }

    protected function getFillableBodyFields(): array
    {
        return [
            'emp_cnpj',
            'data_venda',
            'pagina',
            'registros_por_pagina',
        ];
    }

    /**
     * Retorna o corpo da requisição filtrado pelos campos permitidos
     */
    public function getBody(): array
    {
        $body = data_get($this->integration, 'body', []);

        // Garantir que $body é um array
        if (! is_array($body)) {
            Log::warning('Body não é um array, convertendo para array vazio', ['body' => $body]);
            $body = [];
        }

        // Filtrar apenas os campos permitidos
        $fillableFields = $this->getFillableBodyFields();
        $filteredBody = array_filter(
            $body,
            fn ($key) => in_array($key, $fillableFields),
            ARRAY_FILTER_USE_KEY
        );

        // Body filtrado silenciosamente

        return $filteredBody;
    }

    /**
     * Trata resposta específica da API Visão
     * Sobrescreve o método do BaseApiService para tratar erros 400 específicos
     */
    protected function handleResponse($response): ?array
    {
        $status = $response->status();

        if ($response->successful()) {
            $data = $response->json();

            return $this->extractPagination($data);
        }

        // Trata erro 400 com mensagem "Nenhum produto foi encontrado" como fim de paginação
        if ($status === 400) {
            $data = $response->json();
            $mensagem = $data['mensagem'] ?? '';

            if (stripos($mensagem, 'Nenhum produto foi encontrado') !== false ||
                stripos($mensagem, 'Nenhuma venda foi encontrada') !== false) {
                Log::info('Fim de paginação detectado (erro 400)', ['mensagem' => $mensagem]);

                return [
                    'page' => 0,
                    'per_page' => 0,
                    'total' => 0,
                    'data' => [],
                ];
            }
        }

        if ($status === 401) {
            Log::warning('Credenciais inválidas na API Visão');
        }

        Log::error('Erro na requisição API Visão', [
            'status' => $status,
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
