<?php

namespace App\Services\Import;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Classe base para leitura de arquivos Excel
 * 
 * Fornece métodos utilitários para:
 * - Abrir arquivos Excel
 * - Listar abas disponíveis
 * - Ler dados de uma aba específica
 * - Retornar dados estruturados
 */
class ExcelReader
{
    protected Spreadsheet $spreadsheet;
    protected string $filePath;

    /**
     * Construtor
     * 
     * @param string $filePath Caminho completo do arquivo Excel
     * @throws \Exception Se o arquivo não existir ou não puder ser lido
     */
    public function __construct(string $filePath)
    {
        // Verifica se a extensão ZipArchive está disponível
        if (! extension_loaded('zip') || ! class_exists('ZipArchive')) {
            throw new \Exception('A extensão PHP ZipArchive não está disponível. Por favor, instale a extensão zip.');
        }

        if (! file_exists($filePath)) {
            throw new \Exception("Arquivo não encontrado: {$filePath}");
        }

        $this->filePath = $filePath;
        $this->spreadsheet = IOFactory::load($filePath);
    }

    /**
     * Lista todas as abas disponíveis no arquivo
     * 
     * @return array Array com os nomes das abas
     */
    public function getSheetNames(): array
    {
        return $this->spreadsheet->getSheetNames();
    }

    /**
     * Obtém uma aba específica pelo nome
     * 
     * @param string $sheetName Nome da aba
     * @return Worksheet|null Retorna a aba ou null se não encontrada
     */
    public function getSheetByName(string $sheetName): ?Worksheet
    {
        try {
            return $this->spreadsheet->getSheetByName($sheetName);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Obtém a aba ativa
     * 
     * @return Worksheet
     */
    public function getActiveSheet(): Worksheet
    {
        return $this->spreadsheet->getActiveSheet();
    }

    /**
     * Lê os dados de uma aba específica
     * 
     * @param string|null $sheetName Nome da aba (opcional, usa aba ativa se não informado)
     * @param int $startRow Linha inicial (padrão: 1)
     * @param bool $hasHeader Se a primeira linha contém cabeçalhos (padrão: true)
     * @return Collection Collection com os dados, onde cada item é um array associativo
     */
    public function readSheet(?string $sheetName = null, int $startRow = 1, bool $hasHeader = true): Collection
    {
        $worksheet = $sheetName 
            ? $this->getSheetByName($sheetName) 
            : $this->getActiveSheet();

        if (! $worksheet) {
            throw new \Exception("Aba '{$sheetName}' não encontrada no arquivo");
        }

        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

        $data = collect();

        // Lê cabeçalhos se houver
        $headers = [];
        if ($hasHeader && $startRow === 1) {
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $cellValue = $worksheet->getCell($columnLetter . '1')->getValue();
                $headers[] = $this->normalizeHeader($cellValue ?? '');
            }
            $startRow = 2; // Próxima linha após cabeçalho
        } else {
            // Se não tem cabeçalho, cria nomes genéricos
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $headers[] = "col_{$col}";
            }
        }

        // Lê as linhas de dados
        for ($row = $startRow; $row <= $highestRow; $row++) {
            $rowData = [];
            $isEmpty = true;

            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $cellValue = $worksheet->getCell($columnLetter . $row)->getValue();
                
                // Verifica se a linha está vazia
                if ($cellValue !== null && trim($cellValue) !== '') {
                    $isEmpty = false;
                }

                $rowData[$headers[$col - 1]] = $cellValue ?? '';
            }

            // Adiciona apenas linhas não vazias
            if (! $isEmpty) {
                $data->push($rowData);
            }
        }

        return $data;
    }

    /**
     * Normaliza o nome do cabeçalho removendo espaços e caracteres especiais
     * 
     * @param string $header Nome original do cabeçalho
     * @return string Nome normalizado
     */
    protected function normalizeHeader(string $header): string
    {
        $normalized = trim($header);
        $normalized = strtolower($normalized);
        $normalized = str_replace([' ', '-', '_'], '_', $normalized);
        $normalized = preg_replace('/[^a-z0-9_]/', '', $normalized);
        $normalized = preg_replace('/_+/', '_', $normalized);
        $normalized = trim($normalized, '_');

        return $normalized ?: 'col_' . uniqid();
    }

    /**
     * Retorna o caminho do arquivo
     * 
     * @return string
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }
}

