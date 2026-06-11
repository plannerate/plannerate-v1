<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Services\Editor;

use Callcocam\LaravelRaptorPlannerate\Concerns\UsesPlannerateTenantDatabase;
use Illuminate\Support\Facades\Log;

/**
 * Service para operações de negócio relacionadas a Products (Produtos).
 *
 * Acessa o banco tenant diretamente via UsesPlannerateTenantDatabase — a antiga
 * camada de Repositories foi absorvida aqui (era um wrapper fino de query builder).
 */
class ProductService
{
    use UsesPlannerateTenantDatabase;

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
            default => false
        };
    }

    /**
     * Atualiza dimensões do produto.
     *
     * Nota: dimensões são armazenadas diretamente na tabela products
     * (a tabela dimensions foi removida).
     *
     * @param  array<string, mixed>  $data
     */
    public function updateDimensions(string $productId, array $data): bool
    {
        // Verifica se há dimensões para atualizar
        if (! isset($data['product_dimension']) || ! is_array($data['product_dimension'])) {
            return false;
        }

        // Extrai apenas os campos de dimensão suportados
        $dimensionUpdates = array_intersect_key(
            $data['product_dimension'],
            array_flip(['width', 'height', 'depth', 'weight', 'unit']),
        );

        if (empty($dimensionUpdates)) {
            return false;
        }

        // Busca produto
        $product = $this->plannerateTenantTable('products')->where('id', $productId)->first();
        if (! $product) {
            Log::warning('⚠️ Produto não encontrado', ['product_id' => $productId]);

            return false;
        }

        // Se não tiver unit definido, define como 'cm' por padrão
        if (! isset($dimensionUpdates['unit']) && ! $product->unit) {
            $dimensionUpdates['unit'] = 'cm';
        }

        $this->plannerateTenantTable('products')
            ->where('id', $productId)
            ->update($dimensionUpdates);

        Log::info('✅ Dimensões do produto atualizadas', [
            'product_id' => $productId,
            'updates' => $dimensionUpdates,
        ]);

        return true;
    }
}
