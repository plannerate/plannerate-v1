<?php

namespace App\Services\Files\Exports\Categories;

use App\Services\Files\Exports\Contracts\SpreadsheetExportService;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CategoryExportService implements SpreadsheetExportService
{
    public function downloadTemplate(): BinaryFileResponse
    {
        return Excel::download(
            new CategoriesWorkbookExport([new CategoriesTemplateExport]),
            'categorias-modelo.xlsx'
        );
    }

    public function downloadData(string $tenantId): BinaryFileResponse
    {
        return Excel::download(
            new CategoriesWorkbookExport([new CategoriesDataExport($tenantId)]),
            'categorias-dados.xlsx'
        );
    }
}
