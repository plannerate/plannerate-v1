<?php

namespace App\Services\Import;

use App\Models\Product;
use Callcocam\LaravelRaptor\Support\Import\Contracts\AfterProcessHookInterface;
use Illuminate\Support\Facades\Log;

/**
 * Hook executado ao final da importação da sheet "Tabela mercadológico".
 *
 * Relaciona o id da categoria (último nível salvo na hierarquia) com o produto
 * pelo EAN: para cada linha processada, atualiza product.category_id onde
 * tenant_id + ean batem.
 */
class CategoriasAfterProcess implements AfterProcessHookInterface
{
    public function afterProcess(string $sheetName, array $completedRows): void
    {
        if ($sheetName !== 'Tabela mercadológico') {
            return;
        }

        foreach ($completedRows as $item) {
            $data = $item['data'] ?? [];
            $categoryId = $data['id'] ?? null;
            $ean = $data['ean'] ?? null;
            $tenantId = $data['tenant_id'] ?? null;

            if ($categoryId === null || $ean === null || $tenantId === null) {
                continue;
            }
          Log::info('CategoriasAfterProcess', [
            'categoryId' => $categoryId,
            'ean' => $ean,
            'tenantId' => $tenantId,
          ]);
            Product::query()
                ->where('tenant_id', $tenantId)
                ->where('ean', $ean)
                ->update(['category_id' => $categoryId]);
        }
    }
}
