<?php

namespace App\Services\Files\Imports\Categories;

use App\Services\Files\Imports\Contracts\SpreadsheetImportService;
use App\Services\Files\Imports\ImportExecutionResult;

class CategorySpreadsheetImportService implements SpreadsheetImportService
{
    public function __construct(
        private readonly CategorySpreadsheetReader $reader,
        private readonly CategoryHierarchyImportService $hierarchyImporter
    ) {}

    public function importFile(string $absolutePath, string $tenantId, ?string $userId): ImportExecutionResult
    {
        $rows = $this->reader->read($absolutePath, 'Categorias');

        return $this->hierarchyImporter->importRows($tenantId, $userId, $rows);
    }
}
