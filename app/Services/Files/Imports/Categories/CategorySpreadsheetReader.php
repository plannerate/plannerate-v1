<?php

namespace App\Services\Files\Imports\Categories;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class CategorySpreadsheetReader
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function read(string $absolutePath, string $sheetName = 'Categorias'): array
    {
        $spreadsheet = IOFactory::load($absolutePath);
        $sheet = $spreadsheet->getSheetByName($sheetName);

        if ($sheet === null) {
            throw new RuntimeException("Folha {$sheetName} nao encontrada na planilha.");
        }

        $rows = $sheet->toArray(null, true, true, true);
        if ($rows === []) {
            return [];
        }

        $headerRow = array_shift($rows);
        if (! is_array($headerRow)) {
            return [];
        }

        $headers = [];
        foreach ($headerRow as $column => $header) {
            $headers[$column] = (string) $header;
        }

        $result = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $mapped = [];
            foreach ($headers as $column => $header) {
                if ($header === '') {
                    continue;
                }

                $mapped[$header] = $row[$column] ?? null;
            }

            if ($mapped !== []) {
                $result[] = $mapped;
            }
        }

        return $result;
    }
}
