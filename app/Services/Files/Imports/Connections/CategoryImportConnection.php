<?php

namespace App\Services\Files\Imports\Connections;

use App\Models\Category;
use App\Services\Files\Imports\ImportExecutionResult;

interface CategoryImportConnection
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
    ): void;
}
