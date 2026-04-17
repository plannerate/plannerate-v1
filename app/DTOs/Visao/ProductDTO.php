<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\DTOs\Visao;

use App\DTOs\BaseDTOProcessor;
use Illuminate\Support\Facades\Log;

/**
 * DTO para produtos da API Visão
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

        // Processa GTIN (campo direto, não array)
        $ean = $this->getProcessedValue('gtin', $product, null);
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
        // Monta categoria completa (departamento > segmento > subsegmento)
        $category = collect([
            $this->getProcessedValue('departamento_descricao', $rawData),
            $this->getProcessedValue('segmento_descricao', $rawData),
            $this->getProcessedValue('subsegmento_descricao', $rawData),
        ])->filter()->implode(' > ');

        return [
            'type' => null,
            'reference' => $this->getProcessedValue('produto', $rawData),
            'fragrance' => null,
            'flavor' => null,
            'color' => null,
            'brand' => $this->getProcessedValue('marca_descricao', $rawData),
            'subbrand' => null,
            'packaging_type' => null,
            'packaging_content' => null,
            'unit_measure' => $this->getProcessedValue('unidade_medida', $rawData),
            'auxiliary_description' => $this->getProcessedValue('descricao_reduzida', $rawData),
            'additional_information' => $category,
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
                $supplierDocument = preg_replace('/\D/', '', $this->getProcessedValue('cpf_cnpj', $supplier, ''));

                // ID determinístico para provider baseado no documento
                $providerId = $this->generateChildId('provider', 'document', $supplierDocument);

                // Dados do provider (tabela providers)
                $providers[] = [
                    'id' => $providerId,
                    'tenant_id' => $this->getTenantId(),
                    'user_id' => $this->getUserId(),
                    'code' => $this->getProcessedValue('codigo', $supplier),
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
                    'codigo_erp' => $this->getProcessedValue('codigo', $supplier),
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
        $ean = $this->getProcessedValue('gtin', $item, null);
        if (empty($ean)) {
           // Log::warning("GTIN_VAZIO: Campo gtin está vazio ou não informado para produto {$productCode}");

            return false;
        }

        if (strlen($ean) > 13) {
            // Log::warning('GTIN_MUITO_LONGO: GTIN com '.strlen($ean)." caracteres para produto {$productCode}: {$ean}");

            return false;
        }

        // Verifica flag de ativação
        $cadastroAtivo = $this->getProcessedValue('cadastro_ativo', $item);
        if ($cadastroAtivo === 'N') {
           // Log::warning("cadastro_inativo: Flag cadastro_ativo = N (produto inativo) para produto {$productCode}");

            return false;
        }

        // Filtra produtos que não pertencem ao mix
        $pertenceAoMix = $this->getProcessedValue('pertence_ao_mix', $item);
        if ($pertenceAoMix === 'nao_pertence_ao_mix') {
            // Log::info("produto_fora_do_mix: Produto {$productCode} não pertence ao mix e será ignorado");

            return false;
        }

        return true;
    }

    /**
     * Processa valor de status do produto
     */
    protected function getProcessedStatusValue(array $rawData): string
    {
        $cadastroAtivo = $this->getProcessedValue('cadastro_ativo', $rawData);

        return $cadastroAtivo === 'S' ? 'published' : 'draft';
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
        $uniqueKey = $productId.'|'.$type.'|'.$identifier;

        // Gerar hash determinístico
        $hash = md5($uniqueKey);

        // Criar ID no formato ULID (26 chars)
        $timeComponent = strtoupper(base_convert(time(), 10, 36));
        $hashComponent = strtoupper(substr($hash, 0, 26 - strlen($timeComponent)));

        return str_pad($timeComponent.$hashComponent, 26, '0', STR_PAD_LEFT);
    }
}
