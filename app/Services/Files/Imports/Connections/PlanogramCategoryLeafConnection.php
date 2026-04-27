<?php

namespace App\Services\Files\Imports\Connections;

use App\Models\Category;
use App\Services\Files\Imports\ImportExecutionResult;

class PlanogramCategoryLeafConnection implements CategoryImportConnection
{
    /**
     * Ponto de extensao para futuras conexoes de planograma.
     *
     * @param  array<string, mixed>  $row
     */
    public function connect(
        string $tenantId,
        ?string $userId,
        Category $leafCategory,
        array $row,
        ImportExecutionResult $result
    ): void {
        unset($tenantId, $userId, $leafCategory, $row, $result);
    }
}
