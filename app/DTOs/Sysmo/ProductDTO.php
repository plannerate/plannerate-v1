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
 * DTO para produtos da API Sysmo
 */
class ProductDTO extends BaseDTOProcessor
{
    public function process(array $data, array $params = []): array
    {
        $this->params = $params;
        return $this->processSingleProduct($data) ?? [];
    }

    /**
     * Processa um único produto
     */
    private function processSingleProduct(array $product): ?array
    {
        // Validação consolidada
        if (! $this->validateImportData($product)) {
            return null;
        }

        // Processa GTIN
        $ean = $this->getProcessedGtin('gtins', $product, null);
        if (empty($ean) || strlen($ean) > 13) {
            return null;
        }

        // ID determinístico baseado em EAN + tenant
        $productId = $this->generateProductId($ean, $this->getTenantId());

        // Obtém codigo_erp da API - deve ser válido (não vazio, não 'N/A')
        $codigoErp = $this->getProcessedValue('produto', $product);
        $codigoErp = $this->validateCodigoErp($codigoErp);

        // Mescla dados adicionais diretamente no produto
        $productData = $this->buildProductData($product, $ean, $productId);
        $additionalData = $this->getProcessedAdditionalData($productId, $product, $ean);

        // Mescla dados adicionais no produto
        $productData = array_merge($productData, $additionalData);

        return [
            'product' => $productData,
            'ean' => $ean,
            'client_product' => [
                'client_id' => $this->getClientId(),
                'product_id' => $productId,
                'codigo_erp' => $codigoErp, // Pode ser null se inválido
                'created_at' => now()->format('Y-m-d H:i:s'),
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ],
            'product_store' => [
                'store_id' => $this->getStoreId(),
                'product_id' => $productId,
                'sync_date' => now()->format('Y-m-d'),
                'created_at' => now()->format('Y-m-d H:i:s'),
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ],
            'providers' => $this->getProcessedProviders($product, $productId, $ean),
        ];
    }

    /**
     * Constrói os dados do produto principal
     */
    private function buildProductData(array $product, string $ean, string $productId): array
    {
        return [
            'id' => $productId,
            'tenant_id' => $this->getTenantId(),
            'user_id' => $this->getUserId(),
            'client_id' => $this->getClientId(),
            'store_id' => $this->getStoreId(),
            'ean' => $ean,
            'codigo_erp' => null,
            'name' => $this->getProcessedValue('descricao', $product),
            'description' => $this->getProcessedValue('descricao_comercial', $product),
            'status' => $this->getProcessedStatusValue($product),
            'stackable' => 0,
            'perishable' => 0,
            'flammable' => 0,
            'hangable' => 0,
            'current_stock' => $this->getProcessedValue('estoque.disponivel', $product), // Será atualizado posteriormente
            'created_at' => now()->format('Y-m-d H:i:s'),
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Processa dados adicionais do produto
     * Retorna apenas os campos de dados adicionais (sem metadados)
     */
    protected function getProcessedAdditionalData(string $productId, array $rawData, string $ean): array
    {
        return [
            'type' => $this->getProcessedValue('type', $rawData),
            'reference' => $this->getProcessedValue('reference', $rawData),
            'fragrance' => $this->getProcessedValue('fragrance', $rawData),
            'flavor' => $this->getProcessedValue('flavor', $rawData),
            'color' => $this->getProcessedValue('color', $rawData),
            'brand' => $this->getProcessedValue('marca.descricao', $rawData),
            'subbrand' => $this->getProcessedValue('submarca_descricao', $rawData),
            'packaging_type' => $this->getProcessedValue('tipo_embalagem', $rawData),
            'packaging_content' => $this->getProcessedValue('conteudo_embalagem', $rawData),
            'unit_measure' => $this->getProcessedValue('unidade_venda_descricao', $rawData),
            'auxiliary_description' => $this->getProcessedValue('descricao_reduzida', $rawData),
            'additional_information' => $this->getProcessedValue('ecommerce_info_adicional', $rawData),
        ];
    }

    /**
     * Processa fornecedores do produto
     * Retorna array com providers (tabela providers) e pivots (tabela product_provider)
     */
    protected function getProcessedProviders(array $rawData, string $productId, string $ean): array
    {
        $suppliers = $this->getProcessedValue('fornecedores', $rawData, []);
        $providers = [];
        $pivots = [];

        if (is_array($suppliers) && count($suppliers) > 0) {
            foreach ($suppliers as $index => $supplier) {
                $supplierDocument = $this->getProcessedValue('cpf_cnpj', $supplier, '');

                // ID determinístico para provider baseado no documento
                $providerId = $this->generateChildId('provider', 'document', $supplierDocument);

                // Dados do provider (tabela providers)
                $providers[] = [
                    'id' => $providerId,
                    'tenant_id' => $this->getTenantId(),
                    'user_id' => $this->getUserId(),
                    'code' => $supplierDocument,
                    'name' => $this->getProcessedValue('razao_social', $supplier),
                    'description' => $this->getProcessedValue('fantasia', $supplier),
                    'cnpj' => $supplierDocument,
                    'status' => 'published',
                    'created_at' => now()->format('Y-m-d H:i:s'),
                    'updated_at' => now()->format('Y-m-d H:i:s'),
                ];

                // Dados da pivot (tabela product_provider)
                $pivots[] = [
                    'product_id' => $productId,
                    'provider_id' => $providerId,
                    'principal' => $this->getProcessedValue('principal', $supplier, 'N'),
                    'created_at' => now()->format('Y-m-d H:i:s'),
                    'updated_at' => now()->format('Y-m-d H:i:s'),
                ];
            }
        }

        return [
            'providers' => $providers,
            'pivots' => $pivots,
        ];
    }

    /**
     * Validação consolidada e otimizada com logs detalhados
     */
    protected function validateImportData(array $item): bool
    {
        $productCode = $this->getProcessedValue('produto', $item, 'N/A');

        // Verifica GTIN primeiro (mais comum de falhar)
        if (empty($item['gtins'])) {
            // Log::warning("GTIN_VAZIO: Campo gtins está vazio ou não informado para produto {$productCode}");

            return false;
        }

        // Processa GTIN para validação adicional
        $ean = $this->getProcessedGtin('gtins', $item, null);
        if (empty($ean)) {
            // Log::warning("GTIN_INVALIDO: GTIN não pôde ser processado para produto {$productCode}: " . json_encode($item['gtins']));

            return false;
        }

        if (strlen($ean) > 13) {
            // Log::warning('GTIN_MUITO_LONGO: GTIN com ' . strlen($ean) . " caracteres para produto {$productCode}: {$ean}");

            return false;
        }

        // Verifica flags de ativação
        $requiredFlags = [
            'cadastro_ativo' => 'cadastro_inativo',
            'ativo_na_empresa' => 'inativo_na_empresa',
            'pertence_ao_mix' => 'nao_pertence_ao_mix',
        ];

        foreach ($requiredFlags as $flag => $rejectionKey) {
            $value = $this->getProcessedValue($flag, $item);
            if ($value === 'N') {
                // Log::warning("{$rejectionKey}: Flag {$flag} = N (produto inativo) para produto {$productCode}");

                return false;
            }
        }
        return true;
    }

    /**
     * Processa valor de status do produto
     */
    protected function getProcessedStatusValue(array $rawData): string
    {
        $flags = ['cadastro_ativo', 'ativo_na_empresa', 'pertence_ao_mix'];

        foreach ($flags as $flag) {
            if ($this->getProcessedValue($flag, $rawData) === 'N') {
                return 'draft';
            }
        }

        return 'published';
    }

    /**
     * Valida e limpa codigo_erp
     * Retorna null se inválido (vazio, 'N/A', etc)
     */
    protected function validateCodigoErp(?string $codigoErp): ?string
    {
        if (empty($codigoErp)) {
            return null;
        }

        // Remove espaços e converte para string
        $codigoErp = trim((string) $codigoErp);

        // Valores inválidos que não devem ser salvos
        $invalidValues = ['N/A', 'n/a', 'NA', 'na', 'NULL', 'null', 'NONE', 'none', '-', ''];

        if (in_array($codigoErp, $invalidValues, true)) {
            return null;
        }

        return $codigoErp;
    }

    /**
     * Gera ID determinístico para tabelas filhas
     */
    private function generateChildId(string $productId, string $type, string $identifier = ''): string
    {
        // Chave única para registros filhos
        $uniqueKey = $productId . '|' . $type . '|' . $identifier;

        // Gerar hash determinístico
        $hash = md5($uniqueKey);

        // Criar ID no formato ULID (26 chars)
        $timeComponent = strtoupper(base_convert(time(), 10, 36));
        $hashComponent = strtoupper(substr($hash, 0, 26 - strlen($timeComponent)));

        return str_pad($timeComponent . $hashComponent, 26, '0', STR_PAD_LEFT);
    }
}
