<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\DTOs\Sysmo;

use App\DTOs\BaseDTOProcessor;
use Illuminate\Support\Facades\Log;

/**
 * DTO para vendas da API Sysmo
 */
class SaleDTO extends BaseDTOProcessor
{
    public function process(array $data, array $params = []): array
    {
        $this->params = $params;

        if (! $this->validateImportData($data)) {
            return [];
        }

        return $this->buildSaleData($data);
    }

    /**
     * Constrói os dados da venda baseado na estrutura Sysmo
     */
    private function buildSaleData(array $sale): array
    {
        $deterministicId = $this->generateDeterministicUlid($sale);
        $totalSaleValue = $this->convertToFloat(data_get($sale, 'valor_liquido'));

        return [
            'id' => $deterministicId,
            'client_id' => $this->getClientId(),
            'tenant_id' => $this->getTenantId(),
            'store_id' => $this->getStoreId(),
            'product_id' => null,
            'ean' => null,
            'codigo_erp' => data_get($sale, 'produto'),
            'acquisition_cost' => $this->convertToFloat(data_get($sale, 'custo_aquisicao')),
            'sale_price' => $this->convertToFloat(data_get($sale, 'valor_liquido')),
            'total_profit_margin' => $this->convertToFloat(data_get($sale, 'custo_comercial')),
            'sale_date' => $this->parseDate(data_get($sale, 'data_venda')),
            'promotion' => data_get($sale, 'promocao'),
            'total_sale_quantity' => $this->convertToFloat(data_get($sale, 'quantidade')), // Decimal para suportar vendas por peso
            'total_sale_value' => $totalSaleValue,
            'margem_contribuicao' => $this->calculateMargemContribuicao($sale, $totalSaleValue),
            'extra_data' => json_encode($this->getExtraData($sale)),
            'created_at' => now()->format('Y-m-d H:i:s'),
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Gera ID determinístico baseado em campos únicos da venda
     */
    private function generateDeterministicUlid(array $sale): string
    {
        // Normalizar campos críticos para unicidade
        $produto = preg_replace('/[^A-Za-z0-9]/', '', (string) data_get($sale, 'produto'));
        $dataVenda = preg_replace('/[^0-9]/', '', (string) $this->parseDate(data_get($sale, 'data_venda')));
        $promocao = strtoupper(trim((string) data_get($sale, 'promocao', 'N')));

        // Criar chave única determinística
        $uniqueKey = implode('|', [
            $this->getTenantId(),
            $this->getClientId(),
            $this->getIntegrationId(),
            $produto,
            $dataVenda,
            $promocao,
        ]);

        // Hash determinístico (mesmo input => mesmo output)
        $hash = hash('sha256', $uniqueKey);

        // Criar ID fixo de 26 caracteres (prefixo + 24 chars do hash)
        $prefix = 'S1';
        $hashComponent = strtoupper(substr($hash, 0, 24));

        return $prefix.$hashComponent;
    }

    /**
     * Calcula a margem de contribuição da venda
     *
     * Fórmula: margem_contribuicao = total_sale_value - valor_impostos - custo_medio_loja
     */
    private function calculateMargemContribuicao(array $sale, ?float $totalSaleValue): ?float
    {
        if ($totalSaleValue === null) {
            return null;
        }

        $valorImpostos = $this->convertToFloat(data_get($sale, 'valor_impostos', 0)) ?? 0.0;
        $custoMedioLoja = $this->convertToFloat(data_get($sale, 'custo_medio_loja', 0)) ?? 0.0;

        $margemContribuicao = $totalSaleValue - $valorImpostos - $custoMedioLoja;

        return round($margemContribuicao, 2);
    }

    /**
     * Extrai dados extras não mapeados
     */
    private function getExtraData(array $sale): array
    {
        $mainFields = [];
        $extraData = [];

        foreach ($sale as $key => $value) {
            if (! in_array($key, $mainFields) && ! empty($value)) {
                $extraData[$key] = $value;
            }
        }

        return $extraData;
    }

    /**
     * Validação consolidada e otimizada
     */
    protected function validateImportData(array $item): bool
    {
        // Verifica produto
        if (empty(data_get($item, 'produto'))) {
            Log::warning('N/A', ['code' => 'PRODUTO_VAZIO', 'message' => 'Campo produto está vazio']);

            return false;
        }

        // Verifica data de venda
        if (empty(data_get($item, 'data_venda'))) {
            Log::warning(data_get($item, 'produto', 'N/A'), ['code' => 'DATA_VENDA_VAZIA', 'message' => 'Campo data_venda está vazio']);

            return false;
        }

        return true;
    }
}
