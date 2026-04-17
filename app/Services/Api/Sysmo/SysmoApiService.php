<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Services\Api\Sysmo;

use App\Services\Api\BaseApiService;
use Illuminate\Support\Facades\Log;

/**
 * Serviço para integração com a API da Sysmo
 *
 * Implementa todas as funcionalidades específicas da API Sysmo:
 * - Autenticação via token/API key
 * - Estrutura de resposta específica da Sysmo
 * - Endpoints para produtos, vendas, etc.
 * - Tratamento de erros específicos da Sysmo
 */
class SysmoApiService extends BaseApiService
{
    /**
     * Timeout específico para Sysmo (30 segundos)
     */
    protected int $timeout = 30;

    /**
     * Headers específicos da Sysmo
     */
    protected array $defaultHeaders = [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
        'User-Agent' => 'Laravel-Integration/1.0',
    ];

    /**
     * Endpoints disponíveis na API Sysmo
     */
    private const ENDPOINTS = [
        'products' => 'sysmo-integrador-api/api/integradorService/hubprodutos.listar_produtos',
        'product' => 'sysmo-integrador-api/api/integradorService/hubprodutos.consultar_produto',
        'sales' => 'sysmo-integrador-api/api/integradorService/hubvendas.vendas_produtos',
        'categories' => 'sysmo-integrador-api/api/integradorService/hubprodutos.listar_produtos',
        'suppliers' => 'sysmo-integrador-api/api/integradorService/hubfornecedores.listar_fornecedores',
        'customers' => 'sysmo-integrador-api/api/integradorService/hubclientes.listar_clientes',
    ];

    /**
     * Descobre paginação buscando página por página até encontrar o fim
     * Busca enquanto houver dados, independente de total_paginas
     * Despacha ProcessIntegrationDataJob a cada página para evitar jobs gigantes
     *
     * @param  string  $type  Tipo de dados (products, sales, purchase)
     * @param  array  $params  Parâmetros da requisição
     * @param  int|null  $maxPages  Limite de páginas (null = sem limite)
     * @param  callable|null  $callback  Callback para processar cada página (recebe $data, $pageNumber)
     */
    public function discoverPagination($type, array $params = [], ?int $maxPages = null, ?callable $callback = null): array
    {
        Log::info('Requisição feita para SysmoApiService discoverPagination', [
            'type' => $type,
            'params' => $params,
            'max_pages' => $maxPages,
        ]);

        $totalItems = 0;
        $pagesProcessed = 0;
        $pagina = $params['pagina'] ?? 1;
        $tamanhoPagina = $params['tamanho_pagina'] ?? 1000;
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
                'tamanho_pagina' => $tamanhoPagina,
            ]);

            $response = $this->makeRequest($this->getEndpoint($type), $paginaParams);

            if (! $response) {
                Log::warning("Sem resposta na página {$pagina}. Encerrando paginação.");
                $temMaisPaginas = false;
                break;
            }

            $data = $response['data'] ?? [];
            $qtd = is_array($data) ? count($data) : 0;

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
     * @param  string  $dateField  Campo de data (não usado, Sysmo usa data_inicial/final)
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
        $tamanhoPagina = 10000; // Paginação alta para garantir tudo em 1 página

        $paginaParams = array_merge($params, [
            'data_inicial' => $date,
            'data_final' => $date, // Mesmo dia para Sysmo
            'pagina' => $pagina,
            'tamanho_pagina' => $tamanhoPagina,
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
     * @param  string  $dateField  Campo de data (não usado na Sysmo)
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
            throw new \InvalidArgumentException("Endpoint {$name} não encontrado na API Sysmo.");
        }

        return self::ENDPOINTS[$name];
    }

    /**
     * Extrai informações de paginação da resposta
     * A API Sysmo retorna os dados em 'dados'
     */
    protected function extractPagination(array $response): ?array
    {
        // Lógica para extrair informações de paginação da resposta
        if (empty($response)) {
            return null;
        }

        // A API Sysmo retorna os dados em 'dados'
        $dados = data_get($response, 'dados', []);

        // Se houver informações de paginação, mantém para referência
        $paginaInfo = [];
        if (isset($response['pagina'])) {
            $paginaInfo['page'] = $response['pagina'];
        }
        if (isset($response['total_paginas'])) {
            $paginaInfo['total_pages'] = $response['total_paginas'];
        }

        return array_merge([
            'page' => 1,
            'per_page' => count($dados),
            'total' => count($dados),
            'data' => $dados,
        ], $paginaInfo);
    }

    /**
     * Método para nome do methodo de GET
     */
    protected function getHttpMethod(): string
    {
        return 'POST';
    }

    /**
     * Valida se a integração Sysmo está configurada corretamente
     */
    public function validateIntegration(): bool
    {
        // Verifica se é uma integração Sysmo
        if (data_get($this->integration, 'integration_type') !== 'sysmo') {
            Log::error('Tipo de integração incorreto para SysmoApiService', [
                'expected' => 'sysmo',
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
     * Método basico de autenticação via Basic Auth
     */
    public function getBasicAuthCredentials(): array
    {
        $username = data_get($this->integration, 'headers.authorization.username');
        $password = data_get($this->integration, 'headers.authorization.password');

        // Retorna array indexado para funcionar com spread operator (...)
        return [$username, $password];
    }

    protected function getFillableBodyFields(): array
    {
        return [
            'empresa',
            'pagina',
            'tamanho_pagina',
            'tipo_consulta',
            'partner_key',
            'data_inicial',
            'data_final',
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
}
