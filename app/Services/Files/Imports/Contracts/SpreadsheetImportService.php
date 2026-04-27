<?php

namespace App\Services\Files\Imports\Contracts;

use App\Services\Files\Imports\ImportExecutionResult;

interface SpreadsheetImportService
{
    public function importFile(string $absolutePath, string $tenantId, ?string $userId): ImportExecutionResult;
}
