<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Services\Export;

use App\Exports\ProductMultiSheetExport;
use App\Models\Category;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductRepository;
use App\Services\Export\Concerns\ExportsToExcel;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Exportação de produtos em 4 abas:
 * - Tabela de produtos (principal): ean + dados básicos
 * - Tabela dimensões: ean + dimensões
 * - Tabela dados adicionais: ean + dados adicionais
 * - Tabela mercadológico: ean + estrutura mercadológica (Segmento varejista → Subsegmento)
 * EAN em todas as abas para relacionamento.
 */
final class ProductExportService
{
    use ExportsToExcel;

    public function __construct(
        protected ProductRepository $repository,
        protected CategoryRepository $categoryRepository
    ) {}

    public static function make(): self
    {
        return app(self::class);
    }

    protected function getFileNamePrefix(): string
    {
        return 'produtos';
    }

    protected function getResourceName(): string
    {
        return 'Produtos';
    }

    protected function getExportModelName(): string
    {
        return 'Product';
    }

    /**
     * Gera o arquivo Excel (3 abas) e notifica o usuário (uso pelo Job).
     *
     * @param  array<string, mixed>  $filters
     * @return int Total de linhas (baseado na aba principal)
     */
    public function exportToFile(
        array $filters,
        string $filePath,
        string $fileName,
        string $resourceName,
        int|string|null $userId
    ): int {
        $products = $this->repository->getForExport($filters);
        $categories = $this->categoryRepository->getForExport($filters);
        $idToCategory = $categories->keyBy('id');

        $sheetProdutos = $this->buildSheetProdutos($products);
        $sheetDimensoes = $this->buildSheetDimensoes($products);
        $sheetDadosAdicionais = $this->buildSheetDadosAdicionais($products);
        $sheetMercadologico = $this->buildSheetMercadologico($products, $idToCategory);

        $export = new ProductMultiSheetExport(
            collect($sheetProdutos['rows']),
            $sheetProdutos['headings'],
            collect($sheetDimensoes['rows']),
            $sheetDimensoes['headings'],
            collect($sheetDadosAdicionais['rows']),
            $sheetDadosAdicionais['headings'],
            collect($sheetMercadologico['rows']),
            $sheetMercadologico['headings']
        );

        $disk = config('raptor.export.disk', 'public');
        Excel::store($export, $filePath, $disk);

        $totalRows = count($sheetProdutos['rows']);
        $this->notifyAndDispatchEvent(
            $fileName,
            $filePath,
            $resourceName,
            $totalRows,
            $userId,
            $this->getExportModelName(),
            true
        );

        return $totalRows;
    }

    /**
     * Tabela de produtos: Codigo ERP, Ean, Descricao (campo name).
     * Permite export vazio (só cabeçalhos) para uso como modelo.
     */
    protected function buildSheetProdutos(Collection $products): array
    {
        $headings = ['Codigo ERP', 'Ean', 'Descricao'];
        $rows = [];
        foreach ($products as $p) {
            $rows[] = [
                $p->codigo_erp ?? '',
                $p->ean ?? '',
                $p->name ?? '',
            ];
        }

        return ['headings' => $headings, 'rows' => $rows];
    }

    /**
     * Tabela dimensões: ean + Altura, Largura, Profundidade, Peso, Unidade, Referência.
     */
    protected function buildSheetDimensoes(Collection $products): array
    {
        $headings = ['Ean', 'Altura', 'Largura', 'Profundidade', 'Peso', 'Unidade', 'Referência'];
        $rows = [];
        foreach ($products as $p) {
            $rows[] = [
                $p->ean ?? '',
                $p->height ?? '',
                $p->width ?? '',
                $p->depth ?? '',
                $p->weight ?? '',
                $p->unit ?? '',
                $p->reference ?? '',
            ];
        }

        return ['headings' => $headings, 'rows' => $rows];
    }

    /**
     * Tabela dados adicionais: ean + Fragrância, Sabor, Cor, Marca, Submarca, Tipo de embalagem,
     * Tamanho ou quantidade da embalagem, Unidade de medida, Unidade de medida, Descrição auxiliar, Informação adicional.
     */
    protected function buildSheetDadosAdicionais(Collection $products): array
    {
        $headings = [
            'Ean',
            'Fragrância',
            'Sabor',
            'Cor',
            'Marca',
            'Submarca',
            'Tipo de embalagem',
            'Tamanho ou quantidade da embalagem',
            'Unidade de medida',
            'Unidade de medida',
            'Descrição auxiliar',
            'Informação adicional',
        ];
        $rows = [];
        foreach ($products as $p) {
            $rows[] = [
                $p->ean ?? '',
                $p->fragrance ?? '',
                $p->flavor ?? '',
                $p->color ?? '',
                $p->brand ?? '',
                $p->subbrand ?? '',
                $p->packaging_type ?? '',
                $p->packaging_size ?? '',
                $p->measurement_unit ?? '',
                $p->unit_measure ?? '',
                $p->auxiliary_description ?? '',
                $p->additional_information ?? '',
            ];
        }

        return ['headings' => $headings, 'rows' => $rows];
    }

    /**
     * Tabela mercadológico: ean + estrutura hierárquica (Segmento varejista, Departamento, … Subsegmento).
     *
     * @param  Collection<int, \App\Models\Product>  $products
     * @param  Collection<string, Category>  $idToCategory
     * @return array{headings: array<int, string>, rows: array<int, array<int, string>>}
     */
    protected function buildSheetMercadologico(Collection $products, Collection $idToCategory): array
    {
        $levelColumns = CategoryExportService::LEVEL_COLUMNS;
        $headingLabels = CategoryExportService::HEADING_LABELS;
        $headings = array_merge(['Ean'], array_values($headingLabels));
        $rows = [];

        foreach ($products as $product) {
            $ean = $product->ean ?? '';
            $row = [$ean];

            $category = $product->category;
            if ($category && $idToCategory->has($category->id)) {
                $hierarchy = $this->hierarchyFromMap($category, $idToCategory);
                $levelValues = [];
                foreach ($levelColumns as $levelKey) {
                    $levelValues[$levelKey] = '';
                }
                foreach ($hierarchy as $index => $cat) {
                    $levelKey = $levelColumns[$index] ?? null;
                    if ($levelKey !== null) {
                        $levelValues[$levelKey] = $cat->name ?? '';
                    }
                }
                $row = array_merge($row, array_values($levelValues));
            } else {
                $row = array_merge($row, array_fill(0, count($levelColumns), ''));
            }

            $rows[] = $row;
        }

        return ['headings' => $headings, 'rows' => $rows];
    }

    /**
     * Retorna a hierarquia da categoria (raiz → folha) usando o mapa de categorias.
     *
     * @param  Collection<string, Category>  $idToCategory
     * @return array<int, Category>
     */
    protected function hierarchyFromMap(Category $category, Collection $idToCategory): array
    {
        $path = [];
        $current = $category;

        while ($current !== null) {
            array_unshift($path, $current);
            $current = $current->category_id ? $idToCategory->get($current->category_id) : null;
        }

        return $path;
    }
}
