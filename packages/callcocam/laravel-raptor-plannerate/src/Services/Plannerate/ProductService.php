<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Services\Plannerate;

use Callcocam\LaravelRaptorPlannerate\Repositories\Plannerate\ProductRepository;
use Illuminate\Support\Facades\Log;

/**
 * Service para operações de negócio relacionadas a Products (Produtos)
 */
class ProductService
{
    public function __construct(
        private ProductRepository $productRepository
    ) {}

    /**
     * Cria ou atualiza um produto baseado no tipo de mudança
     *
     * @param  array<string, mixed>  $change
     */
    public function createOrUpdate(array $change): bool
    {
        $type = $change['type'];

        return match ($type) {
            'product_update' => $this->updateDimensions($change['entityId'], $change['data']),
            'product_placement' => $this->place($change['data']),
            default => false
        };
    }

    /**
     * Atualiza dimensões do produto
     *
     * Nota: Dimensões são armazenadas diretamente na tabela products (tabela dimensions foi removida)
     *
     * @param  array<string, mixed>  $data
     */
    public function updateDimensions(string $productId, array $data): bool
    {
        // Verifica se há dimensões para atualizar
        if (! isset($data['product_dimension']) || ! is_array($data['product_dimension'])) {
            return false;
        }

        $dimensionUpdates = [];

        // Extrai width, height, depth, weight, unit
        if (isset($data['product_dimension']['width'])) {
            $dimensionUpdates['width'] = $data['product_dimension']['width'];
        }
        if (isset($data['product_dimension']['height'])) {
            $dimensionUpdates['height'] = $data['product_dimension']['height'];
        }
        if (isset($data['product_dimension']['depth'])) {
            $dimensionUpdates['depth'] = $data['product_dimension']['depth'];
        }
        if (isset($data['product_dimension']['weight'])) {
            $dimensionUpdates['weight'] = $data['product_dimension']['weight'];
        }
        if (isset($data['product_dimension']['unit'])) {
            $dimensionUpdates['unit'] = $data['product_dimension']['unit'];
        }

        if (empty($dimensionUpdates)) {
            return false;
        }

        // Busca produto
        $product = $this->productRepository->find($productId);
        if (! $product) {
            Log::warning('⚠️ Produto não encontrado', ['product_id' => $productId]);

            return false;
        }

        // Se não tiver unit definido, define como 'cm' por padrão
        if (! isset($dimensionUpdates['unit']) && ! $product->unit) {
            $dimensionUpdates['unit'] = 'cm';
        }

        $this->productRepository->update($product, $dimensionUpdates);

        Log::info('✅ Dimensões do produto atualizadas', [
            'product_id' => $productId,
            'updates' => $dimensionUpdates,
        ]);

        return true;
    }

    /**
     * Posiciona produto (implementar se necessário)
     *
     * Placeholder para lógica de posicionamento de produtos
     *
     * @param  array<string, mixed>  $data
     */
    public function place(array $data): bool
    {
        // TODO: Implementar lógica de posicionamento se necessário
        Log::info('ℹ️ product_placement chamado (não implementado)', ['data' => $data]);

        return true;
    }
}
