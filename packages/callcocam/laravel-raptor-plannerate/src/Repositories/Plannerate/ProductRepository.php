<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Repositories\Plannerate;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Repository para operações de acesso a dados de Products (Produtos)
 */
class ProductRepository
{
    private const REPO = 'ProductRepository';

    /**
     * Busca um produto por ID
     */
    public function find(string $productId): ?object
    {
        try {
            return DB::connection(config('database.default'))->table('products')->where('id', $productId)->first();
        } catch (\Throwable $e) {
            Log::error('Plannerate repository failed', [
                'repository' => self::REPO,
                'method' => 'find',
                'product_id' => $productId,
                'connection' => config('database.default'),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Atualiza um produto
     *
     * @param  object  $product  O produto retornado pelo find()
     * @param  array<string, mixed>  $data  Dados para atualizar
     */
    public function update(object $product, array $data): bool
    {
        try {
            $width = data_get($data, 'width', $product->width);
            $height = data_get($data, 'height', $product->height);
            $depth = data_get($data, 'depth', $product->depth);
            Log::info('✅ Dimensões do produto atualizadas', ['product_id' => $product->id, 'updates' => $data]);
            // has_dimensions não existe como coluna; é derivado de width/height/depth
            unset($data['has_dimensions']);

            return DB::connection(config('database.default'))
                ->table('products')
                ->where('id', $product->id)
                ->update($data) > 0;
        } catch (\Throwable $e) {
            Log::error('Plannerate repository failed', [
                'repository' => self::REPO,
                'method' => 'update',
                'product_id' => $product->id ?? null,
                'connection' => config('database.default'),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function isDimensionValid(mixed $value): bool
    {
        return is_numeric($value) && $value > 0;
    }
}
