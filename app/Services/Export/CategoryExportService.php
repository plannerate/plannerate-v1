<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Services\Export;

use App\Exports\CategoryHierarchyExport;
use App\Models\Category;
use App\Repositories\CategoryRepository;
use App\Services\Export\Concerns\ExportsToExcel;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Colunas da exportação, espelhando a importação hierárquica (Tabela mercadológico).
 * level_name e nivel definem em qual coluna cada categoria entra.
 */
final class CategoryExportService
{
    use ExportsToExcel;

    public const SHEET_NAME = 'Tabela mercadológico';

    /** Nomes dos níveis na ordem (índice = nivel). */
    public const LEVEL_COLUMNS = [
        'segmento_varejista',
        'departamento',
        'subdepartamento',
        'categoria',
        'subcategoria',
        'segmento',
        'subsegmento',
    ];

    /** Labels para o cabeçalho do Excel (mesmo padrão da importação). */
    public const HEADING_LABELS = [
        'segmento_varejista' => 'Segmento varejista',
        'departamento' => 'Departamento',
        'subdepartamento' => 'Subdepartamento',
        'categoria' => 'Categoria',
        'subcategoria' => 'Subcategoria',
        'segmento' => 'Segmento',
        'subsegmento' => 'Subsegmento',
    ];

    public function __construct(
        protected CategoryRepository $repository
    ) {}

    public static function make(): self
    {
        return app(self::class);
    }

    protected function getFileNamePrefix(): string
    {
        return 'categorias';
    }

    protected function getResourceName(): string
    {
        return 'Categorias';
    }

    protected function getExportModelName(): string
    {
        return 'Category';
    }

    /**
     * Gera o arquivo Excel e notifica o usuário (uso pelo Job).
     *
     * @param  array<string, mixed>  $filters
     * @return int Total de linhas exportadas
     */
    public function exportToFile(
        array $filters,
        string $filePath,
        string $fileName,
        string $resourceName,
        int|string|null $userId
    ): int {
        $categories = $this->repository->getForExport($filters);
        $rows = $this->buildRows($categories);
        $headings = array_values(self::HEADING_LABELS);

        $export = new CategoryHierarchyExport(
            collect($rows),
            $headings,
            self::SHEET_NAME
        );

        $disk = config('raptor.export.disk', 'public');
        Excel::store($export, $filePath, $disk);

        $totalRows = count($rows);
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
     * Monta uma linha por categoria com as colunas hierárquicas (level_name -> name).
     *
     * @param  Collection<int, Category>  $categories
     * @return array<int, array<string, string>>
     */
    public function buildRows(Collection $categories): array
    {
        $idToCategory = $categories->keyBy('id');
        $rows = [];

        foreach ($categories as $category) {
            $hierarchy = $this->hierarchyFromMap($category, $idToCategory);
            $row = [];
            foreach (self::LEVEL_COLUMNS as $levelKey) {
                $row[$levelKey] = '';
            }
            foreach ($hierarchy as $index => $cat) {
                $levelKey = self::LEVEL_COLUMNS[$index] ?? null;
                if ($levelKey !== null) {
                    $row[$levelKey] = $cat->name ?? '';
                }
            }
            $rows[] = array_values($row);
        }

        return $rows;
    }

    /**
     * Retorna a hierarquia da categoria (raiz -> folha) usando o mapa de categorias.
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
