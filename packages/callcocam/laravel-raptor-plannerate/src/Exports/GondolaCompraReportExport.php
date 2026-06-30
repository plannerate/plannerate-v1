<?php

namespace Callcocam\LaravelRaptorPlannerate\Exports;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GondolaCompraReportExport
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

        // Definir título da planilha (máximo 31 caracteres)
        $gondolaName = $this->reportData['gondola_name'] ?? 'Gôndola';
        $title = 'Compra - '.$gondolaName;
        // Truncar se necessário para caber em 31 caracteres
        if (strlen($title) > 31) {
            $maxGondolaLength = 31 - strlen('Compra - ');
            $gondolaName = substr($gondolaName, 0, $maxGondolaLength);
            $title = 'Compra - '.$gondolaName;
        }
        $sheet->setTitle($title);

        // Criar resumo executivo (mesmo do atual)
        $this->createSummary($sheet);

        // Criar cabeçalhos específicos para compra
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
     * Cria o resumo executivo (idêntico ao atual)
     */
    private function createSummary($sheet): void
    {
        $summary = $this->reportData['summary'] ?? [];
        $products = $this->reportData['products'] ?? [];
        $gondolaName = $this->reportData['gondola_name'] ?? 'N/A';

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
     * Cria os cabeçalhos específicos para relatório de compra
     */
    private function createHeaders($sheet): void
    {
        $headers = [
            'A7' => 'Código ERP',
            'B7' => 'EAN',
            'C7' => 'Descrição',
            'D7' => 'Departamento',
            'E7' => 'Categoria',
            'F7' => 'Total Unidades',
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

        // Agrupar produtos por código ERP para somar total de unidades
        $groupedProducts = [];
        foreach ($gondolaProducts as $product) {
            $key = $product['codigo_erp'] ?? '';
            if (! isset($groupedProducts[$key])) {
                $groupedProducts[$key] = [
                    'codigo_erp' => $product['codigo_erp'] ?? '',
                    'ean' => $product['ean'] ?? '',
                    'nome' => $product['nome'] ?? '',
                    'departamento' => $product['departamento'] ?? 'N/A',
                    'categoria' => $product['categoria'] ?? 'Produto',
                    'total_unidades' => 0,
                ];
            }
            $groupedProducts[$key]['total_unidades'] += (int) ($product['total_unidades'] ?? 0);
        }

        // Processar em lotes para otimizar performance
        $batchSize = 20; // Processar 20 produtos por vez
        $chunks = array_chunk($groupedProducts, $batchSize);

        foreach ($chunks as $chunk) {
            foreach ($chunk as $product) {
                // Preencher colunas específicas para compra
                $sheet->setCellValue("A{$row}", $product['codigo_erp']);
                $sheet->setCellValue("C{$row}", $product['nome']);
                $sheet->setCellValue("D{$row}", $product['departamento']);
                $sheet->setCellValue("E{$row}", $product['categoria']);

                // Tratar EAN como texto para evitar notação científica
                $ean = $product['ean'];
                if (is_numeric($ean)) {
                    $sheet->setCellValueExplicit("B{$row}", $ean, DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue("B{$row}", $ean);
                }

                // Total de unidades - forçar como número
                $sheet->setCellValueExplicit("F{$row}", (int) $product['total_unidades'], DataType::TYPE_NUMERIC);

                $row++;
            }

            // Forçar garbage collection a cada lote para liberar memória
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }
    }

    /**
     * Aplica estilos e formatações na planilha (idêntico ao atual)
     */
    private function applyStyles($sheet): void
    {
        // Contar produtos únicos agrupados (apenas da gôndola)
        $products = $this->reportData['products'] ?? [];
        $gondolaProducts = array_filter($products, function ($product) {
            return ($product['source'] ?? 'gondola') === 'gondola';
        });

        $groupedProducts = [];
        foreach ($gondolaProducts as $product) {
            $key = $product['codigo_erp'] ?? '';
            if (! isset($groupedProducts[$key])) {
                $groupedProducts[$key] = true;
            }
        }
        $totalRows = count($groupedProducts) + 7; // +7 para resumo e cabeçalho

        // Estilo do título do resumo (idêntico)
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

        // Estilo das labels do resumo (idêntico)
        $labelStyle = [
            'font' => [
                'bold' => true,
                'size' => 9,
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

        // Estilo dos valores do resumo (idêntico)
        $valueStyle = [
            'font' => [
                'bold' => false,
                'size' => 10,
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

        // Estilo para valores numéricos do resumo (idêntico)
        $numericStyle = [
            'font' => [
                'bold' => true,
                'size' => 12,
            ],
        ];
        $sheet->getStyle('B5:C5')->applyFromArray($numericStyle);
        $sheet->getStyle('F3')->applyFromArray($numericStyle);
        $sheet->getStyle('F4:F5')->applyFromArray($numericStyle);

        // Estilo do cabeçalho da tabela (idêntico)
        $sheet->getStyle('A7:F7')->applyFromArray([
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
                    'color' => ['argb' => '000000'],
                ],
            ],
        ]);

        // Bordas e fonte para dados da tabela
        $sheet->getStyle("A8:F{$totalRows}")->applyFromArray([
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
                'size' => 10,
            ],
        ]);

        // Centralizar todas as colunas de dados
        $sheet->getStyle("A8:F{$totalRows}")->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Formatação numérica para coluna de total unidades
        $sheet->getStyle("F8:F{$totalRows}")->applyFromArray([
            'numberFormat' => [
                'formatCode' => '#,##0',
            ],
        ]);

        // Formatação específica do EAN como texto
        $sheet->getStyle("B8:B{$totalRows}")->getNumberFormat()->setFormatCode('@');
    }

    /**
     * Define as larguras das colunas
     */
    private function setColumnWidths($sheet): void
    {
        $widths = [
            'A' => 17, // Código ERP
            'B' => 15, // EAN
            'C' => 40, // Descrição (nome)
            'D' => 20, // Departamento
            'E' => 20, // Categoria
            'F' => 15, // Total Unidades
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
        // Contar produtos únicos agrupados (apenas da gôndola)
        $products = $this->reportData['products'] ?? [];
        $gondolaProducts = array_filter($products, function ($product) {
            return ($product['source'] ?? 'gondola') === 'gondola';
        });

        $groupedProducts = [];
        foreach ($gondolaProducts as $product) {
            $key = $product['codigo_erp'] ?? '';
            if (! isset($groupedProducts[$key])) {
                $groupedProducts[$key] = true;
            }
        }
        $totalRows = count($groupedProducts) + 7;

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
