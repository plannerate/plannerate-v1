<?php

namespace App\Services\Files\Exports\Categories;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class CategoriesTemplateExport implements FromArray, WithHeadings, WithTitle
{
    /**
     * @return array<int, string>
     */
    public static function columns(): array
    {
        return [
            'Segmento varejista',
            'Departamento',
            'Subdepartamento',
            'Categoria',
            'Subcategoria',
            'Segmento',
            'Subsegmento',
            'Atributo',
            'ean',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return self::columns();
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return [];
    }

    public function title(): string
    {
        return 'Categorias';
    }
}
