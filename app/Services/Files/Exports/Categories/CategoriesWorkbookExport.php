<?php

namespace App\Services\Files\Exports\Categories;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CategoriesWorkbookExport implements WithMultipleSheets
{
    /**
     * @param  array<int, object>  $sheets
     */
    public function __construct(
        private readonly array $sheets
    ) {}

    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        return $this->sheets;
    }
}
