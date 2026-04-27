<?php

namespace App\Services\Files\Exports\Contracts;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

interface SpreadsheetExportService
{
    public function downloadTemplate(): BinaryFileResponse;

    public function downloadData(string $tenantId): BinaryFileResponse;
}
