<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ProductMultiSheetExport implements WithMultipleSheets
{
    public function __construct(
        protected Collection $sheetProdutosRows,
        protected array $sheetProdutosHeadings,
        protected Collection $sheetDimensoesRows,
        protected array $sheetDimensoesHeadings,
        protected Collection $sheetDadosAdicionaisRows,
        protected array $sheetDadosAdicionaisHeadings,
        protected Collection $sheetMercadologicoRows,
        protected array $sheetMercadologicoHeadings
    ) {}

    public function sheets(): array
    {
        return [
            new ProductSheetExport($this->sheetProdutosRows, $this->sheetProdutosHeadings, 'Tabela de produtos'),
            new ProductSheetExport($this->sheetDimensoesRows, $this->sheetDimensoesHeadings, 'Tabela dimensões'),
            new ProductSheetExport($this->sheetDadosAdicionaisRows, $this->sheetDadosAdicionaisHeadings, 'Tabela dados adicionais'),
            new ProductSheetExport($this->sheetMercadologicoRows, $this->sheetMercadologicoHeadings, 'Tabela mercadológico'),
        ];
    }
}
