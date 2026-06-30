<?php

namespace Callcocam\LaravelRaptorPlannerate\Exports;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GondolaReportExport
{
    protected $reportData;

    public function __construct(array $reportData)
    {
        $this->reportData = $reportData;
    }

    /**
     * Gera o arquivo Excel e retorna uma resposta de download
     */
    public function download(string $filename): StreamedResponse
    {
        $spreadsheet = $this->createSpreadsheet();

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Cria a planilha com todos os dados e formatações
     */
    private function createSpreadsheet(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        // Definir título da planilha
        $gondolaName = str($this->reportData['gondola_name'] ?? 'Gôndola')->substr(0, 15);
        $sheet->setTitle("Relatório - {$gondolaName}");

        // Criar resumo executivo
        $this->createSummary($sheet);

        // Criar cabeçalhos
        $this->createHeaders($sheet);

        // Preencher dados
        $this->fillData($sheet);

        // Aplicar formatações
        $this->applyStyles($sheet);

        // Definir larguras das colunas
        $this->setColumnWidths($sheet);

        // Definir altura das linhas
        $this->setRowHeights($sheet);

        // Ocultar linhas de grade (gridlines)
        $sheet->setShowGridlines(false);

        return $spreadsheet;
    }

    /**
     * Cria o resumo executivo com melhor distribuição
     */
    private function createSummary($sheet): void
    {
        $summary = $this->reportData['summary'] ?? [];
        $products = $this->reportData['products'] ?? [];
        $gondolaName = str($this->reportData['gondola_name'] ?? 'Gôndola')->substr(0, 25);

        // Título principal
        $sheet->setCellValue('A1', 'RESUMO EXECUTIVO');
        $sheet->mergeCells('A1:F1');

        // Layout reorganizado em 3 linhas
        // Linha 1
        $sheet->setCellValue('A3', 'Planograma:');
        $sheet->setCellValue('B3', $gondolaName);
        $sheet->mergeCells('B3:C3');

        $sheet->setCellValue('D3', 'Total de Módulos:');
        $sheet->mergeCells('D3:E3');
        $sheet->setCellValue('F3', $summary['total_sections'] ?? 0);

        // Linha 2 - Fluxo embaixo do planograma
        $sheet->setCellValue('A4', 'Fluxo:');
        $sheet->setCellValue('B4', $products[0]['fluxo'] ?? 'N/A');
        $sheet->mergeCells('B4:C4');

        // Filtrar apenas produtos da gôndola para o resumo
        $gondolaProducts = array_filter($products, function ($product) {
            return ($product['source'] ?? 'gondola') === 'gondola';
        });

        $sheet->setCellValue('D4', 'Total Produtos:');
        $sheet->mergeCells('D4:E4');
        $sheet->setCellValue('F4', count($gondolaProducts));

        // Linha 3 - Dados em 2 colunas
        $sheet->setCellValue('A5', 'Total de Prateleiras:');
        $sheet->setCellValue('B5', $summary['total_shelves'] ?? 0);
        $sheet->mergeCells('B5:C5');

        $sheet->setCellValue('D5', 'Total Unidades:');
        $sheet->mergeCells('D5:E5');
        $sheet->setCellValue('F5', array_sum(array_column($gondolaProducts, 'total_unidades')));
    }

    /**
     * Cria os cabeçalhos da planilha
     */
    private function createHeaders($sheet): void
    {
        $headers = [
            'A7' => 'Módulo',
            'B7' => 'Prateleira',
            'C7' => 'Código ERP',
            'D7' => 'Ean',
            'E7' => 'Nome',
            'F7' => 'Frentes',
            'G7' => 'Empilhamento',
            'H7' => 'Und. Prof',
            'I7' => 'Total Unid',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }
    }

    /**
     * Preenche os dados na planilha
     */
    private function fillData($sheet): void
    {
        $products = $this->reportData['products'] ?? [];
        $row = 8; // Começar na linha 8 (depois do resumo e cabeçalho)

        // Filtrar apenas produtos que estão na gôndola (não da biblioteca)
        $gondolaProducts = array_filter($products, function ($product) {
            return ($product['source'] ?? 'gondola') === 'gondola';
        });

        // Ordenar produtos por módulo considerando o fluxo da gôndola
        $gondolaProducts = $this->sortProductsByFlow($gondolaProducts);

        foreach ($gondolaProducts as $product) {
            // Preencher colunas (sem nome do planograma)
            $sheet->setCellValue("A{$row}", $product['modulo'] ?? '');
            $sheet->setCellValue("B{$row}", $product['prateleira'] ?? '');
            $sheet->setCellValue("C{$row}", $product['codigo_erp'] ?? '');
            $sheet->setCellValue("E{$row}", $product['nome'] ?? '');

            // Tratar EAN como texto para evitar notação científica
            $ean = $product['ean'] ?? '';
            if (is_numeric($ean)) {
                $sheet->setCellValueExplicit("D{$row}", $ean, DataType::TYPE_STRING);
            } else {
                $sheet->setCellValue("D{$row}", $ean);
            }

            // Campos numéricos - forçar como números
            $sheet->setCellValueExplicit("F{$row}", (int) ($product['frentes'] ?? 0), DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit("G{$row}", (int) ($product['unidades_altura'] ?? 0), DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit("H{$row}", (int) ($product['unidades_profundidade'] ?? 0), DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit("I{$row}", (int) ($product['total_unidades'] ?? 0), DataType::TYPE_NUMERIC);

            $row++;
        }
    }

    /**
     * Ordena produtos por módulo considerando o fluxo da gôndola
     */
    private function sortProductsByFlow($products): array
    {
        if (empty($products)) {
            return $products;
        }

        $flow = $products[0]['fluxo'] ?? 'left_to_right';

        // Log para debug
        error_log('Fluxo detectado: '.$flow);

        // Encontrar o número máximo de módulos
        $maxModule = 0;
        foreach ($products as $product) {
            $modulo = $product['modulo'] ?? '';
            preg_match('/MÓDULO (\d+)/', $modulo, $matches);
            $moduleNumber = isset($matches[1]) ? (int) $matches[1] : 0;
            $maxModule = max($maxModule, $moduleNumber);
        }

        error_log('Número máximo de módulos: '.$maxModule);

        // Processar cada produto
        $processedProducts = [];
        foreach ($products as $product) {
            $modulo = $product['modulo'] ?? '';
            preg_match('/MÓDULO (\d+)/', $modulo, $matches);
            $originalModuleNumber = isset($matches[1]) ? (int) $matches[1] : 0;

            // Se o fluxo é da direita para esquerda, renumerar os módulos
            if (strpos($flow, 'right_to_left') !== false ||
                strpos($flow, 'Direita para Esquerda') !== false ||
                strpos($flow, 'right') !== false) {

                // Renumerar: módulo 1 vira o último, módulo 2 vira o penúltimo, etc.
                $newModuleNumber = $maxModule - $originalModuleNumber + 1;
                $product['modulo'] = 'MÓDULO '.$newModuleNumber;
                error_log("Módulo $originalModuleNumber -> MÓDULO $newModuleNumber (fluxo right_to_left)");
            }

            $processedProducts[] = $product;
        }

        // Ordenar por número do módulo e depois por número da prateleira (crescente)
        usort($processedProducts, function ($a, $b) {
            $moduloA = $a['modulo'] ?? '';
            $moduloB = $b['modulo'] ?? '';

            preg_match('/MÓDULO (\d+)/', $moduloA, $matchesA);
            preg_match('/MÓDULO (\d+)/', $moduloB, $matchesB);

            $moduleA = isset($matchesA[1]) ? (int) $matchesA[1] : 0;
            $moduleB = isset($matchesB[1]) ? (int) $matchesB[1] : 0;

            // Se os módulos são diferentes, ordenar por módulo
            if ($moduleA !== $moduleB) {
                return $moduleA - $moduleB;
            }

            // Se os módulos são iguais, ordenar por prateleira
            $prateleiraA = $a['prateleira'] ?? '';
            $prateleiraB = $b['prateleira'] ?? '';

            preg_match('/Prateleira (\d+)/', $prateleiraA, $matchesA);
            preg_match('/Prateleira (\d+)/', $prateleiraB, $matchesB);

            $shelfA = isset($matchesA[1]) ? (int) $matchesA[1] : 0;
            $shelfB = isset($matchesB[1]) ? (int) $matchesB[1] : 0;

            return $shelfA - $shelfB;
        });

        return $processedProducts;
    }

    /**
     * Aplica estilos e formatações na planilha
     */
    private function applyStyles($sheet): void
    {
        // Contar apenas produtos da gôndola
        $products = $this->reportData['products'] ?? [];
        $gondolaProducts = array_filter($products, function ($product) {
            return ($product['source'] ?? 'gondola') === 'gondola';
        });
        $totalRows = count($gondolaProducts) + 7; // +7 para resumo e cabeçalho

        // Estilo do título do resumo
        $sheet->getStyle('A1:F1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FFFFFF'],
                'size' => 14,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => '080404'], // Verde escuro
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Estilo das labels do resumo - aplicar separadamente
        $labelStyle = [
            'font' => [
                'bold' => true,
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'e9ecef'], // Cinza claro
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => '000000'],
                ],
            ],
        ];

        $sheet->getStyle('A3')->applyFromArray($labelStyle);
        $sheet->getStyle('A4')->applyFromArray($labelStyle);
        $sheet->getStyle('A5')->applyFromArray($labelStyle);
        $sheet->getStyle('D3:E3')->applyFromArray($labelStyle);
        $sheet->getStyle('D4:E4')->applyFromArray($labelStyle);
        $sheet->getStyle('D5:E5')->applyFromArray($labelStyle);

        // Estilo dos valores do resumo
        $valueStyle = [
            'font' => [
                'bold' => false,
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'ffffff'], // Branco
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => '000000'],
                ],
            ],
        ];

        $sheet->getStyle('B3:C3')->applyFromArray($valueStyle);
        $sheet->getStyle('B4:C4')->applyFromArray($valueStyle);
        $sheet->getStyle('B5:C5')->applyFromArray($valueStyle);
        $sheet->getStyle('F3')->applyFromArray($valueStyle);
        $sheet->getStyle('F4:F5')->applyFromArray($valueStyle);

        // Estilo para valores numéricos do resumo
        $numericStyle = [
            'font' => [
                'bold' => true,
                'size' => 12,
            ],
        ];
        $sheet->getStyle('B5:C5')->applyFromArray($numericStyle);
        $sheet->getStyle('F3')->applyFromArray($numericStyle);
        $sheet->getStyle('F4:F5')->applyFromArray($numericStyle);

        // Estilo do cabeçalho da tabela
        $sheet->getStyle('A7:I7')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['argb' => '000000'],
                'size' => 10,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => '9cf737'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => '000000'], // Borda branca para contraste
                ],
            ],
        ]);

        // Bordas e fonte para dados da tabela
        $sheet->getStyle("A8:I{$totalRows}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => '000000'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
                'shrinkToFit' => false,
            ],
            'font' => [
                'size' => 10, // Fonte menor para o conteúdo
            ],
        ]);

        // Centralizar todas as colunas de dados
        $sheet->getStyle("A8:I{$totalRows}")->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Formatação numérica para colunas numéricas
        $sheet->getStyle("F8:I{$totalRows}")->applyFromArray([
            'numberFormat' => [
                'formatCode' => '#,##0',
            ],
        ]);

        // Formatação específica do EAN como texto
        $sheet->getStyle("D8:D{$totalRows}")->getNumberFormat()->setFormatCode('@');
    }

    /**
     * Define as larguras das colunas
     */
    private function setColumnWidths($sheet): void
    {
        $widths = [
            'A' => 25, // Módulo
            'B' => 15, // Prateleira
            'C' => 15, // Código ERP
            'D' => 15, // Ean
            'E' => 30, // Nome
            'F' => 12, // Frentes
            'G' => 15, // Empilhamento
            'H' => 12, // Unidades de Profundidade
            'I' => 15, // Total de unidades
        ];

        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
    }

    /**
     * Define a altura das linhas
     */
    private function setRowHeights($sheet): void
    {
        // Contar apenas produtos da gôndola
        $products = $this->reportData['products'] ?? [];
        $gondolaProducts = array_filter($products, function ($product) {
            return ($product['source'] ?? 'gondola') === 'gondola';
        });
        $totalRows = count($gondolaProducts) + 7;

        // Altura do título do resumo
        $sheet->getRowDimension(1)->setRowHeight(35);

        // Altura das linhas do resumo
        $sheet->getRowDimension(3)->setRowHeight(25);
        $sheet->getRowDimension(4)->setRowHeight(25);
        $sheet->getRowDimension(5)->setRowHeight(25);

        // Altura do cabeçalho da tabela
        $sheet->getRowDimension(7)->setRowHeight(30);

        // Altura das linhas de dados
        for ($row = 8; $row <= $totalRows; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(25);
        }
    }
}
