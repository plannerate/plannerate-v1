<?php

namespace App\Services\Files\Imports\Connections;

use App\Models\Category;
use App\Models\Product;
use App\Services\Files\Imports\ImportExecutionResult;

class ProductCategoryByEanConnection implements CategoryImportConnection
{
    /**
     * @param  array<string, mixed>  $row
     */
    public function connect(
        string $tenantId,
        ?string $userId,
        Category $leafCategory,
        array $row,
        ImportExecutionResult $result
    ): void {
        unset($userId);

        $ean = (string) ($row['ean'] ?? '');
        if ($ean === '') {
            return;
        }

        $product = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('ean', $ean)
            ->first();

        if (! $product instanceof Product) {
            $result->addWarning("Produto nao encontrado para EAN {$ean}.");

            return;
        }

        if ((string) $product->category_id === (string) $leafCategory->id) {
            return;
        }

        $product->update([
            'category_id' => $leafCategory->id,
        ]);

        $result->productsLinked++;
    }
}
